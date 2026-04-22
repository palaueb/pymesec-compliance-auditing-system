package main

import (
	"bufio"
	"bytes"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"time"
)

const defaultSmokeOperationID = "coreGetMcpServerProfile"

type jsonRPCRequest struct {
	JSONRPC string         `json:"jsonrpc"`
	ID      int            `json:"id"`
	Method  string         `json:"method"`
	Params  map[string]any `json:"params,omitempty"`
}

type jsonRPCResponse struct {
	JSONRPC string        `json:"jsonrpc"`
	ID      int           `json:"id,omitempty"`
	Result  any           `json:"result,omitempty"`
	Error   *jsonRPCError `json:"error,omitempty"`
}

type jsonRPCError struct {
	Code    int    `json:"code"`
	Message string `json:"message"`
}

type rpcClient struct {
	reader *bufio.Reader
	writer *bufio.Writer
	nextID int
}

type listedOperation struct {
	OperationID string
	Method      string
	Path        string
}

func main() {
	if err := run(); err != nil {
		fmt.Fprintf(os.Stderr, "mcp smoke failed: %v\n", err)
		os.Exit(1)
	}
}

func run() error {
	var mcpBin string
	var apiBaseURL string
	var apiToken string
	var operationID string
	var requestTimeout time.Duration

	flag.StringVar(&mcpBin, "mcp-bin", strings.TrimSpace(os.Getenv("PYMESEC_MCP_BIN")), "Path to pymesec-mcp binary")
	flag.StringVar(&apiBaseURL, "api-base-url", firstNonEmpty(strings.TrimSpace(os.Getenv("PYMESEC_API_BASE_URL")), "http://127.0.0.1:18080"), "API base URL")
	flag.StringVar(&apiToken, "api-token", strings.TrimSpace(os.Getenv("PYMESEC_API_TOKEN")), "API bearer token")
	flag.StringVar(&operationID, "operation-id", firstNonEmpty(strings.TrimSpace(os.Getenv("PYMESEC_MCP_SMOKE_OPERATION_ID")), defaultSmokeOperationID), "Operation ID for pymesec_call_operation smoke check")
	flag.DurationVar(&requestTimeout, "request-timeout", 30*time.Second, "Per-request timeout for MCP JSON-RPC calls")
	flag.Parse()

	if mcpBin == "" {
		return errors.New("missing --mcp-bin (or PYMESEC_MCP_BIN)")
	}
	if apiToken == "" {
		return errors.New("missing --api-token (or PYMESEC_API_TOKEN)")
	}

	cmd := exec.Command(
		mcpBin,
		"--api-base-url="+apiBaseURL,
		"--api-token="+apiToken,
		"--sync-openapi-on-start=true",
	)

	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	stdin, err := cmd.StdinPipe()
	if err != nil {
		return err
	}

	var stderr bytes.Buffer
	cmd.Stderr = &stderr

	if err := cmd.Start(); err != nil {
		return err
	}

	waitDone := make(chan error, 1)
	go func() {
		waitDone <- cmd.Wait()
	}()
	defer func() {
		_ = stdin.Close()
		select {
		case <-waitDone:
		case <-time.After(2 * time.Second):
			_ = cmd.Process.Kill()
			<-waitDone
		}
	}()

	client := &rpcClient{
		reader: bufio.NewReader(stdout),
		writer: bufio.NewWriter(stdin),
		nextID: 1,
	}

	if _, err := client.callWithTimeout("initialize", map[string]any{
		"protocolVersion": "2025-03-26",
		"capabilities":    map[string]any{},
		"clientInfo": map[string]any{
			"name":    "pymesec-mcp-smoke",
			"version": "1.0.0",
		},
	}, requestTimeout); err != nil {
		return withStderr(err, stderr.String())
	}

	capabilitiesPayload, err := callTool(client, "pymesec_get_capabilities", map[string]any{
		"bearer_token": apiToken,
	}, requestTimeout)
	if err != nil {
		return withStderr(err, stderr.String())
	}

	capStatus, err := extractNestedStatus(capabilitiesPayload, "response")
	if err != nil {
		return withStderr(err, stderr.String())
	}
	if capStatus < 200 || capStatus >= 300 {
		return withStderr(fmt.Errorf("pymesec_get_capabilities returned non-2xx status: %d", capStatus), stderr.String())
	}
	fmt.Printf("OK pymesec_get_capabilities: HTTP %d\n", capStatus)

	opsPayload, err := callTool(client, "pymesec_list_operations", map[string]any{}, requestTimeout)
	if err != nil {
		return withStderr(err, stderr.String())
	}

	ops, err := parseOperations(opsPayload)
	if err != nil {
		return withStderr(err, stderr.String())
	}
	if len(ops) == 0 {
		return withStderr(errors.New("pymesec_list_operations returned zero operations"), stderr.String())
	}
	fmt.Printf("OK pymesec_list_operations: %d operations discovered\n", len(ops))

	selected, err := selectOperation(ops, operationID)
	if err != nil {
		return withStderr(err, stderr.String())
	}
	fmt.Printf("Using operation: %s (%s %s)\n", selected.OperationID, selected.Method, selected.Path)

	callPayload, err := callTool(client, "pymesec_call_operation", map[string]any{
		"bearer_token": apiToken,
		"operation_id": selected.OperationID,
	}, requestTimeout)
	if err != nil {
		return withStderr(err, stderr.String())
	}

	callStatus, err := extractNestedStatus(callPayload, "response")
	if err != nil {
		return withStderr(err, stderr.String())
	}
	fmt.Printf("OK pymesec_call_operation: HTTP %d\n", callStatus)

	apiPayload, err := callTool(client, "pymesec_api_request", map[string]any{
		"bearer_token": apiToken,
		"method":       selected.Method,
		"path":         selected.Path,
	}, requestTimeout)
	if err != nil {
		return withStderr(err, stderr.String())
	}

	apiStatus, err := extractNestedStatus(apiPayload, "response")
	if err != nil {
		return withStderr(err, stderr.String())
	}
	fmt.Printf("OK pymesec_api_request: HTTP %d\n", apiStatus)

	callResponse, err := nestedMap(callPayload, "response")
	if err != nil {
		return withStderr(err, stderr.String())
	}
	apiResponse, err := nestedMap(apiPayload, "response")
	if err != nil {
		return withStderr(err, stderr.String())
	}

	if callStatus != apiStatus {
		return withStderr(
			fmt.Errorf("status mismatch between pymesec_call_operation (%d) and pymesec_api_request (%d)", callStatus, apiStatus),
			stderr.String(),
		)
	}

	if !jsonEqualIgnoringVolatileFields(callResponse["body"], apiResponse["body"]) {
		return withStderr(errors.New("response body mismatch between pymesec_call_operation and pymesec_api_request"), stderr.String())
	}

	fmt.Println("OK parity: pymesec_call_operation and pymesec_api_request returned equivalent response bodies")
	fmt.Println("MCP smoke checks passed.")

	return nil
}

func callTool(client *rpcClient, toolName string, args map[string]any, timeout time.Duration) (map[string]any, error) {
	resp, err := client.callWithTimeout("tools/call", map[string]any{
		"name":      toolName,
		"arguments": args,
	}, timeout)
	if err != nil {
		return nil, err
	}

	if resp.Error != nil {
		return nil, fmt.Errorf("jsonrpc error %d: %s", resp.Error.Code, resp.Error.Message)
	}

	resultMap, ok := resp.Result.(map[string]any)
	if !ok {
		return nil, fmt.Errorf("tool [%s] returned unexpected result shape", toolName)
	}

	if isError, _ := resultMap["isError"].(bool); isError {
		if message := resultText(resultMap); message != "" {
			return nil, fmt.Errorf("tool [%s] returned error: %s", toolName, message)
		}

		return nil, fmt.Errorf("tool [%s] returned error payload", toolName)
	}

	structured, ok := resultMap["structuredContent"].(map[string]any)
	if !ok {
		return nil, fmt.Errorf("tool [%s] missing structuredContent", toolName)
	}

	return structured, nil
}

func (c *rpcClient) callWithTimeout(method string, params map[string]any, timeout time.Duration) (jsonRPCResponse, error) {
	type response struct {
		resp jsonRPCResponse
		err  error
	}

	done := make(chan response, 1)
	go func() {
		resp, err := c.call(method, params)
		done <- response{resp: resp, err: err}
	}()

	select {
	case out := <-done:
		return out.resp, out.err
	case <-time.After(timeout):
		return jsonRPCResponse{}, fmt.Errorf("timeout waiting response for method [%s]", method)
	}
}

func (c *rpcClient) call(method string, params map[string]any) (jsonRPCResponse, error) {
	request := jsonRPCRequest{
		JSONRPC: "2.0",
		ID:      c.nextID,
		Method:  method,
		Params:  params,
	}
	c.nextID++

	if err := writeJSONRPCMessage(c.writer, request); err != nil {
		return jsonRPCResponse{}, err
	}

	return readJSONRPCMessage(c.reader)
}

func writeJSONRPCMessage(writer *bufio.Writer, request jsonRPCRequest) error {
	body, err := json.Marshal(request)
	if err != nil {
		return err
	}

	header := fmt.Sprintf("Content-Length: %d\r\nContent-Type: application/json\r\n\r\n", len(body))
	if _, err := writer.WriteString(header); err != nil {
		return err
	}
	if _, err := writer.Write(body); err != nil {
		return err
	}

	return writer.Flush()
}

func readJSONRPCMessage(reader *bufio.Reader) (jsonRPCResponse, error) {
	line, err := reader.ReadString('\n')
	if err != nil {
		return jsonRPCResponse{}, err
	}

	trimmed := strings.TrimSpace(line)
	if strings.HasPrefix(trimmed, "{") {
		return decodeJSONRPCResponse([]byte(trimmed))
	}

	contentLength := 0
	headerLine := strings.TrimRight(line, "\r\n")
	for {
		if headerLine == "" {
			break
		}

		lower := strings.ToLower(headerLine)
		if strings.HasPrefix(lower, "content-length:") {
			parts := strings.SplitN(headerLine, ":", 2)
			if len(parts) != 2 {
				return jsonRPCResponse{}, errors.New("invalid content-length header")
			}

			n, convErr := strconv.Atoi(strings.TrimSpace(parts[1]))
			if convErr != nil {
				return jsonRPCResponse{}, convErr
			}
			contentLength = n
		}

		nextLine, readErr := reader.ReadString('\n')
		if readErr != nil {
			return jsonRPCResponse{}, readErr
		}
		headerLine = strings.TrimRight(nextLine, "\r\n")
	}

	if contentLength <= 0 {
		return jsonRPCResponse{}, errors.New("missing content-length")
	}

	body := make([]byte, contentLength)
	if _, err := io.ReadFull(reader, body); err != nil {
		return jsonRPCResponse{}, err
	}

	return decodeJSONRPCResponse(body)
}

func decodeJSONRPCResponse(payload []byte) (jsonRPCResponse, error) {
	var response jsonRPCResponse
	if err := json.Unmarshal(payload, &response); err != nil {
		return jsonRPCResponse{}, err
	}
	return response, nil
}

func parseOperations(payload map[string]any) ([]listedOperation, error) {
	rawOps, ok := payload["operations"].([]any)
	if !ok {
		return nil, errors.New("missing operations array in pymesec_list_operations")
	}

	ops := make([]listedOperation, 0, len(rawOps))
	for _, raw := range rawOps {
		opMap, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		id := strings.TrimSpace(anyToString(opMap["operation_id"]))
		method := strings.TrimSpace(strings.ToUpper(anyToString(opMap["method"])))
		path := strings.TrimSpace(anyToString(opMap["path"]))
		if id == "" || method == "" || path == "" {
			continue
		}

		ops = append(ops, listedOperation{
			OperationID: id,
			Method:      method,
			Path:        path,
		})
	}

	return ops, nil
}

func selectOperation(ops []listedOperation, requestedID string) (listedOperation, error) {
	if requestedID != "" {
		for _, op := range ops {
			if op.OperationID == requestedID {
				return op, nil
			}
		}

		return listedOperation{}, fmt.Errorf("requested operation_id [%s] not found in openapi index", requestedID)
	}

	for _, op := range ops {
		if op.OperationID == defaultSmokeOperationID {
			return op, nil
		}
	}

	for _, op := range ops {
		if op.Method == "GET" && !strings.Contains(op.Path, "{") {
			return op, nil
		}
	}

	return ops[0], nil
}

func extractNestedStatus(payload map[string]any, key string) (int, error) {
	response, err := nestedMap(payload, key)
	if err != nil {
		return 0, err
	}

	status, ok := response["status"]
	if !ok {
		return 0, fmt.Errorf("missing [%s.status]", key)
	}

	return anyToInt(status)
}

func nestedMap(payload map[string]any, key string) (map[string]any, error) {
	value, ok := payload[key]
	if !ok || value == nil {
		return nil, fmt.Errorf("missing [%s]", key)
	}

	mapped, ok := value.(map[string]any)
	if !ok {
		return nil, fmt.Errorf("[%s] is not an object", key)
	}

	return mapped, nil
}

func anyToInt(value any) (int, error) {
	switch typed := value.(type) {
	case int:
		return typed, nil
	case int32:
		return int(typed), nil
	case int64:
		return int(typed), nil
	case float32:
		return int(typed), nil
	case float64:
		return int(typed), nil
	case string:
		parsed, err := strconv.Atoi(strings.TrimSpace(typed))
		if err != nil {
			return 0, err
		}
		return parsed, nil
	default:
		return 0, fmt.Errorf("cannot convert type %T to int", value)
	}
}

func anyToString(value any) string {
	if value == nil {
		return ""
	}
	return fmt.Sprintf("%v", value)
}

func jsonEqualIgnoringVolatileFields(left any, right any) bool {
	leftJSON, leftErr := json.Marshal(stripVolatileAPIFields(left))
	rightJSON, rightErr := json.Marshal(stripVolatileAPIFields(right))
	if leftErr != nil || rightErr != nil {
		return false
	}

	return bytes.Equal(leftJSON, rightJSON)
}

func stripVolatileAPIFields(value any) any {
	switch typed := value.(type) {
	case map[string]any:
		out := make(map[string]any, len(typed))
		for key, item := range typed {
			if key == "request_id" {
				continue
			}
			out[key] = stripVolatileAPIFields(item)
		}
		return out
	case []any:
		out := make([]any, 0, len(typed))
		for _, item := range typed {
			out = append(out, stripVolatileAPIFields(item))
		}
		return out
	default:
		return value
	}
}

func resultText(result map[string]any) string {
	content, ok := result["content"].([]any)
	if !ok || len(content) == 0 {
		return ""
	}

	first, ok := content[0].(map[string]any)
	if !ok {
		return ""
	}

	return strings.TrimSpace(anyToString(first["text"]))
}

func withStderr(err error, stderr string) error {
	stderr = strings.TrimSpace(stderr)
	if stderr == "" {
		return err
	}

	return fmt.Errorf("%w (mcp stderr: %s)", err, stderr)
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		trimmed := strings.TrimSpace(value)
		if trimmed != "" {
			return trimmed
		}
	}
	return ""
}
