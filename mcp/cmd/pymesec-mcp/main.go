package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"
)

const (
	defaultProtocolVersion = "2025-03-26"
	defaultAPIPrefix       = "/api/v1"
)

var (
	contentLengthPattern = regexp.MustCompile(`(?i)^Content-Length:\s*(\d+)\s*$`)
	pathParamPattern     = regexp.MustCompile(`\{([^{}]+)\}`)
)

type config struct {
	APIBaseURL   string
	APIPrefix    string
	APIToken     string
	OpenAPIURL   string
	RequestTTL   time.Duration
	StartupSync  bool
	ServerName   string
	ServerVer    string
}

type jsonRPCRequest struct {
	JSONRPC string         `json:"jsonrpc"`
	ID      any            `json:"id,omitempty"`
	Method  string         `json:"method"`
	Params  map[string]any `json:"params,omitempty"`
}

type jsonRPCResponse struct {
	JSONRPC string         `json:"jsonrpc"`
	ID      any            `json:"id,omitempty"`
	Result  any            `json:"result,omitempty"`
	Error   *jsonRPCError  `json:"error,omitempty"`
}

type jsonRPCError struct {
	Code    int            `json:"code"`
	Message string         `json:"message"`
	Data    map[string]any `json:"data,omitempty"`
}

type operation struct {
	OperationID string
	Method      string
	Path        string
	Tags        []string
	Summary     string
}

type openAPIIndex struct {
	ServerURLPath string
	Operations    map[string]operation
}

type mcpServer struct {
	cfg        config
	client     *http.Client
	openAPI    *openAPIIndex
	openAPIErr error
}

func main() {
	cfg, err := loadConfig()
	if err != nil {
		log.Fatalf("configuration error: %v", err)
	}

	server := &mcpServer{
		cfg: cfg,
		client: &http.Client{
			Timeout: cfg.RequestTTL,
		},
	}

	if cfg.StartupSync {
		server.openAPI, server.openAPIErr = server.fetchOpenAPI(context.Background(), cfg.APIToken)
	}

	if err := runJSONRPCLoop(server, os.Stdin, os.Stdout); err != nil {
		log.Fatalf("mcp server stopped with error: %v", err)
	}
}

func loadConfig() (config, error) {
	var cfg config

	flag.StringVar(&cfg.APIBaseURL, "api-base-url", firstNonEmpty(os.Getenv("PYMESEC_API_BASE_URL"), "http://127.0.0.1:8000"), "PymeSec base URL")
	flag.StringVar(&cfg.APIPrefix, "api-prefix", firstNonEmpty(os.Getenv("PYMESEC_API_PREFIX"), defaultAPIPrefix), "PymeSec API prefix")
	flag.StringVar(&cfg.APIToken, "api-token", os.Getenv("PYMESEC_API_TOKEN"), "Default bearer token")
	flag.StringVar(&cfg.OpenAPIURL, "openapi-url", os.Getenv("PYMESEC_OPENAPI_URL"), "OpenAPI URL override")
	flag.DurationVar(&cfg.RequestTTL, "request-timeout", parseDurationEnv("PYMESEC_REQUEST_TIMEOUT", 30*time.Second), "HTTP timeout")
	flag.BoolVar(&cfg.StartupSync, "sync-openapi-on-start", parseBoolEnv("PYMESEC_SYNC_OPENAPI_ON_START", true), "Fetch OpenAPI on startup")
	flag.StringVar(&cfg.ServerName, "server-name", firstNonEmpty(os.Getenv("PYMESEC_MCP_SERVER_NAME"), "PymeSec MCP Server"), "MCP server display name")
	flag.StringVar(&cfg.ServerVer, "server-version", firstNonEmpty(os.Getenv("PYMESEC_MCP_SERVER_VERSION"), "1.0.0"), "MCP server version")
	flag.Parse()

	cfg.APIBaseURL = strings.TrimSpace(cfg.APIBaseURL)
	cfg.APIPrefix = normalizePrefix(cfg.APIPrefix)
	cfg.APIToken = strings.TrimSpace(cfg.APIToken)
	cfg.OpenAPIURL = strings.TrimSpace(cfg.OpenAPIURL)
	cfg.ServerName = strings.TrimSpace(cfg.ServerName)
	cfg.ServerVer = strings.TrimSpace(cfg.ServerVer)

	if cfg.APIBaseURL == "" {
		return cfg, errors.New("api-base-url cannot be empty")
	}

	if _, err := url.ParseRequestURI(cfg.APIBaseURL); err != nil {
		return cfg, fmt.Errorf("invalid api-base-url: %w", err)
	}

	if cfg.OpenAPIURL == "" {
		cfg.OpenAPIURL = strings.TrimRight(cfg.APIBaseURL, "/") + "/openapi/v1.json"
	}

	if cfg.ServerName == "" {
		cfg.ServerName = "PymeSec MCP Server"
	}

	if cfg.ServerVer == "" {
		cfg.ServerVer = "1.0.0"
	}

	return cfg, nil
}

func runJSONRPCLoop(server *mcpServer, input io.Reader, output io.Writer) error {
	reader := bufio.NewReader(input)
	writer := bufio.NewWriter(output)
	defer writer.Flush()

	for {
		req, err := readJSONRPCMessage(reader)
		if err != nil {
			if errors.Is(err, io.EOF) {
				return nil
			}

			resp := jsonRPCResponse{
				JSONRPC: "2.0",
				Error: &jsonRPCError{
					Code:    -32700,
					Message: "Parse error",
					Data: map[string]any{
						"reason": err.Error(),
					},
				},
			}

			if writeErr := writeJSONRPCMessage(writer, resp); writeErr != nil {
				return writeErr
			}

			continue
		}

		resp, hasResponse := server.handle(req)
		if !hasResponse {
			continue
		}

		if err := writeJSONRPCMessage(writer, resp); err != nil {
			return err
		}
	}
}

func readJSONRPCMessage(reader *bufio.Reader) (jsonRPCRequest, error) {
	line, err := reader.ReadString('\n')
	if err != nil {
		return jsonRPCRequest{}, err
	}

	trimmed := strings.TrimSpace(line)

	// Allow JSONL payloads for local debugging tools.
	if strings.HasPrefix(trimmed, "{") {
		return decodeJSONRPCRequest([]byte(trimmed))
	}

	contentLength := 0
	headerLine := strings.TrimRight(line, "\r\n")
	for {
		if headerLine == "" {
			break
		}

		if matches := contentLengthPattern.FindStringSubmatch(headerLine); len(matches) == 2 {
			n, convErr := strconv.Atoi(matches[1])
			if convErr != nil {
				return jsonRPCRequest{}, fmt.Errorf("invalid content length: %w", convErr)
			}
			contentLength = n
		}

		nextLine, readErr := reader.ReadString('\n')
		if readErr != nil {
			return jsonRPCRequest{}, readErr
		}

		headerLine = strings.TrimRight(nextLine, "\r\n")
	}

	if contentLength <= 0 {
		return jsonRPCRequest{}, errors.New("missing Content-Length header")
	}

	body := make([]byte, contentLength)
	if _, err := io.ReadFull(reader, body); err != nil {
		return jsonRPCRequest{}, fmt.Errorf("unable to read jsonrpc payload: %w", err)
	}

	return decodeJSONRPCRequest(body)
}

func decodeJSONRPCRequest(payload []byte) (jsonRPCRequest, error) {
	var req jsonRPCRequest
	if err := json.Unmarshal(payload, &req); err != nil {
		return jsonRPCRequest{}, err
	}

	if strings.TrimSpace(req.Method) == "" {
		return jsonRPCRequest{}, errors.New("jsonrpc method is required")
	}

	if req.Params == nil {
		req.Params = map[string]any{}
	}

	return req, nil
}

func writeJSONRPCMessage(writer *bufio.Writer, response jsonRPCResponse) error {
	body, err := json.Marshal(response)
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

func (s *mcpServer) handle(req jsonRPCRequest) (jsonRPCResponse, bool) {
	if req.Method == "notifications/initialized" {
		return jsonRPCResponse{}, false
	}

	if req.Method == "" {
		return s.errorResponse(req.ID, -32600, "Invalid request"), true
	}

	switch req.Method {
	case "initialize":
		return jsonRPCResponse{
			JSONRPC: "2.0",
			ID:      req.ID,
			Result: map[string]any{
				"protocolVersion": defaultProtocolVersion,
				"serverInfo": map[string]any{
					"name":    s.cfg.ServerName,
					"version": s.cfg.ServerVer,
				},
				"capabilities": map[string]any{
					"tools": map[string]any{
						"listChanged": false,
					},
				},
				"instructions": "MCP tools are API-first and autoconfigure operation routing from OpenAPI.",
			},
		}, true
	case "ping":
		return jsonRPCResponse{
			JSONRPC: "2.0",
			ID:      req.ID,
			Result: map[string]any{
				"pong": true,
			},
		}, true
	case "tools/list":
		return jsonRPCResponse{
			JSONRPC: "2.0",
			ID:      req.ID,
			Result: map[string]any{
				"tools": s.toolsList(),
			},
		}, true
	case "tools/call":
		result, err := s.callTool(req.Params)
		if err != nil {
			return s.errorResponse(req.ID, -32602, err.Error()), true
		}

		return jsonRPCResponse{
			JSONRPC: "2.0",
			ID:      req.ID,
			Result:  result,
		}, true
	default:
		return s.errorResponse(req.ID, -32601, fmt.Sprintf("Method [%s] is not supported.", req.Method)), true
	}
}

func (s *mcpServer) errorResponse(id any, code int, message string) jsonRPCResponse {
	return jsonRPCResponse{
		JSONRPC: "2.0",
		ID:      id,
		Error: &jsonRPCError{
			Code:    code,
			Message: message,
		},
	}
}

func (s *mcpServer) toolsList() []map[string]any {
	return []map[string]any{
		{
			"name":        "pymesec_api_request",
			"description": "Generic authenticated API request against /api/v1.",
			"inputSchema": map[string]any{
				"type":     "object",
				"required": []string{"method", "path"},
				"properties": map[string]any{
					"method": map[string]any{
						"type": "string",
						"enum": []string{"GET", "POST", "PUT", "PATCH", "DELETE"},
					},
					"path": map[string]any{
						"type":    "string",
						"pattern": "^/api/v1/",
					},
					"query": map[string]any{
						"type":                 "object",
						"additionalProperties": true,
					},
					"body": map[string]any{
						"type":                 "object",
						"additionalProperties": true,
					},
					"headers": map[string]any{
						"type": "object",
						"additionalProperties": map[string]any{
							"type": "string",
						},
					},
					"bearer_token": map[string]any{
						"type":        "string",
						"description": "Optional token override.",
					},
				},
			},
		},
		{
			"name":        "pymesec_call_operation",
			"description": "Call an OpenAPI operation by operation_id. Path/method are discovered from OpenAPI.",
			"inputSchema": map[string]any{
				"type":     "object",
				"required": []string{"operation_id"},
				"properties": map[string]any{
					"operation_id": map[string]any{
						"type": "string",
					},
					"path_params": map[string]any{
						"type":                 "object",
						"additionalProperties": true,
					},
					"query": map[string]any{
						"type":                 "object",
						"additionalProperties": true,
					},
					"body": map[string]any{
						"type":                 "object",
						"additionalProperties": true,
					},
					"headers": map[string]any{
						"type": "object",
						"additionalProperties": map[string]any{
							"type": "string",
						},
					},
					"bearer_token": map[string]any{
						"type": "string",
					},
				},
			},
		},
		{
			"name":        "pymesec_list_operations",
			"description": "List discoverable operation IDs loaded from OpenAPI.",
			"inputSchema": map[string]any{
				"type": "object",
				"properties": map[string]any{
					"contains": map[string]any{
						"type":        "string",
						"description": "Optional substring filter for operation_id.",
					},
				},
			},
		},
		{
			"name":        "pymesec_get_capabilities",
			"description": "Get effective capabilities for current authenticated context.",
			"inputSchema": map[string]any{
				"type": "object",
				"properties": map[string]any{
					"organization_id": map[string]any{"type": "string"},
					"scope_id":        map[string]any{"type": "string"},
					"membership_id":   map[string]any{"type": "string"},
					"membership_ids": map[string]any{
						"type":  "array",
						"items": map[string]any{"type": "string"},
					},
					"bearer_token": map[string]any{"type": "string"},
				},
			},
		},
		{
			"name":        "pymesec_get_openapi",
			"description": "Get OpenAPI contract from authenticated API endpoint.",
			"inputSchema": map[string]any{
				"type": "object",
				"properties": map[string]any{
					"bearer_token": map[string]any{"type": "string"},
				},
			},
		},
		{
			"name":        "pymesec_get_mcp_profile",
			"description": "Get official MCP profile metadata from API.",
			"inputSchema": map[string]any{
				"type": "object",
				"properties": map[string]any{
					"bearer_token": map[string]any{"type": "string"},
				},
			},
		},
	}
}

func (s *mcpServer) callTool(params map[string]any) (map[string]any, error) {
	name, _ := params["name"].(string)
	if strings.TrimSpace(name) == "" {
		return nil, errors.New("tool call requires [name]")
	}

	rawArgs := map[string]any{}
	if incoming, ok := params["arguments"].(map[string]any); ok && incoming != nil {
		rawArgs = incoming
	}

	switch name {
	case "pymesec_api_request":
		payload, err := s.toolAPIRequest(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	case "pymesec_call_operation":
		payload, err := s.toolCallOperation(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	case "pymesec_list_operations":
		payload, err := s.toolListOperations(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	case "pymesec_get_capabilities":
		payload, err := s.toolGetCapabilities(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	case "pymesec_get_openapi":
		payload, err := s.toolGetOpenAPI(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	case "pymesec_get_mcp_profile":
		payload, err := s.toolGetMCPProfile(rawArgs)
		if err != nil {
			return nil, err
		}
		return toMCPToolResult(payload, false)
	default:
		return toMCPToolResult(map[string]any{
			"error": map[string]any{
				"code":    "unknown_tool",
				"message": fmt.Sprintf("Tool [%s] is not registered.", name),
			},
		}, true)
	}
}

func toMCPToolResult(payload map[string]any, isError bool) (map[string]any, error) {
	pretty, err := json.MarshalIndent(payload, "", "  ")
	if err != nil {
		return nil, err
	}

	result := map[string]any{
		"content": []map[string]any{
			{
				"type": "text",
				"text": string(pretty),
			},
		},
		"structuredContent": payload,
	}

	if isError {
		result["isError"] = true
	}

	return result, nil
}

func (s *mcpServer) toolAPIRequest(args map[string]any) (map[string]any, error) {
	method := strings.ToUpper(strings.TrimSpace(stringArg(args, "method")))
	path := strings.TrimSpace(stringArg(args, "path"))
	if method == "" {
		return nil, errors.New("missing tool argument [method]")
	}

	if path == "" {
		return nil, errors.New("missing tool argument [path]")
	}

	queryMap := mapArg(args, "query")
	bodyMap := mapArg(args, "body")
	headersMap := stringMapArg(args, "headers")
	token := firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken)

	response, err := s.apiCall(context.Background(), method, path, queryMap, bodyMap, headersMap, token)
	if err != nil {
		return nil, err
	}

	return map[string]any{
		"request": map[string]any{
			"method": method,
			"path":   path,
			"query":  queryMap,
		},
		"response": response,
	}, nil
}

func (s *mcpServer) toolCallOperation(args map[string]any) (map[string]any, error) {
	operationID := strings.TrimSpace(stringArg(args, "operation_id"))
	if operationID == "" {
		return nil, errors.New("missing tool argument [operation_id]")
	}

	index, err := s.ensureOpenAPI(context.Background(), firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken))
	if err != nil {
		return nil, fmt.Errorf("cannot resolve openapi index: %w", err)
	}

	op, ok := index.Operations[operationID]
	if !ok {
		return nil, fmt.Errorf("unknown operation_id [%s]", operationID)
	}

	pathParams := mapArg(args, "path_params")
	queryMap := mapArg(args, "query")
	bodyMap := mapArg(args, "body")
	headersMap := stringMapArg(args, "headers")
	token := firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken)

	resolvedPath, err := resolvePathTemplate(op.Path, pathParams)
	if err != nil {
		return nil, err
	}

	response, err := s.apiCall(context.Background(), op.Method, resolvedPath, queryMap, bodyMap, headersMap, token)
	if err != nil {
		return nil, err
	}

	return map[string]any{
		"operation": map[string]any{
			"operation_id": operationID,
			"method":       op.Method,
			"path":         op.Path,
			"tags":         op.Tags,
			"summary":      op.Summary,
		},
		"resolved_request": map[string]any{
			"path": resolvedPath,
		},
		"response": response,
	}, nil
}

func (s *mcpServer) toolListOperations(args map[string]any) (map[string]any, error) {
	filter := strings.ToLower(strings.TrimSpace(stringArg(args, "contains")))
	index, err := s.ensureOpenAPI(context.Background(), firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken))
	if err != nil {
		return nil, fmt.Errorf("cannot load operation list: %w", err)
	}

	ops := make([]operation, 0, len(index.Operations))
	for _, op := range index.Operations {
		if filter != "" && !strings.Contains(strings.ToLower(op.OperationID), filter) {
			continue
		}
		ops = append(ops, op)
	}

	sort.Slice(ops, func(i, j int) bool {
		return ops[i].OperationID < ops[j].OperationID
	})

	serialized := make([]map[string]any, 0, len(ops))
	for _, op := range ops {
		serialized = append(serialized, map[string]any{
			"operation_id": op.OperationID,
			"method":       op.Method,
			"path":         op.Path,
			"tags":         op.Tags,
			"summary":      op.Summary,
		})
	}

	return map[string]any{
		"count":      len(serialized),
		"operations": serialized,
		"openapi": map[string]any{
			"url":        s.cfg.OpenAPIURL,
			"api_prefix": index.ServerURLPath,
		},
	}, nil
}

func (s *mcpServer) toolGetCapabilities(args map[string]any) (map[string]any, error) {
	query := map[string]any{}

	if value := stringArg(args, "organization_id"); value != "" {
		query["organization_id"] = value
	}
	if value := stringArg(args, "scope_id"); value != "" {
		query["scope_id"] = value
	}
	if value := stringArg(args, "membership_id"); value != "" {
		query["membership_id"] = value
	}
	if values := stringSliceArg(args, "membership_ids"); len(values) > 0 {
		query["membership_ids"] = values
	}

	return s.toolAPIRequest(map[string]any{
		"method":       "GET",
		"path":         defaultAPIPrefix + "/meta/capabilities",
		"query":        query,
		"bearer_token": firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken),
	})
}

func (s *mcpServer) toolGetOpenAPI(args map[string]any) (map[string]any, error) {
	return s.toolAPIRequest(map[string]any{
		"method":       "GET",
		"path":         defaultAPIPrefix + "/openapi",
		"bearer_token": firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken),
	})
}

func (s *mcpServer) toolGetMCPProfile(args map[string]any) (map[string]any, error) {
	return s.toolAPIRequest(map[string]any{
		"method":       "GET",
		"path":         defaultAPIPrefix + "/meta/mcp-server",
		"bearer_token": firstNonEmpty(stringArg(args, "bearer_token"), s.cfg.APIToken),
	})
}

func (s *mcpServer) ensureOpenAPI(ctx context.Context, token string) (*openAPIIndex, error) {
	if s.openAPI != nil {
		return s.openAPI, nil
	}

	index, err := s.fetchOpenAPI(ctx, token)
	if err != nil {
		s.openAPIErr = err
		return nil, err
	}

	s.openAPI = index
	s.openAPIErr = nil
	return s.openAPI, nil
}

func (s *mcpServer) fetchOpenAPI(ctx context.Context, token string) (*openAPIIndex, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, s.cfg.OpenAPIURL, nil)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	req.Header.Set("User-Agent", "PymeSec-MCP-Go/1.0")
	if strings.TrimSpace(token) != "" {
		req.Header.Set("Authorization", "Bearer "+strings.TrimSpace(token))
	}

	resp, err := s.client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		raw, _ := io.ReadAll(io.LimitReader(resp.Body, 8192))
		return nil, fmt.Errorf("openapi fetch failed with status %d: %s", resp.StatusCode, strings.TrimSpace(string(raw)))
	}

	var document map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&document); err != nil {
		return nil, fmt.Errorf("invalid openapi json: %w", err)
	}

	serverPath := s.cfg.APIPrefix
	if servers, ok := document["servers"].([]any); ok {
		for _, rawServer := range servers {
			server, ok := rawServer.(map[string]any)
			if !ok {
				continue
			}
			serverURL, _ := server["url"].(string)
			if strings.TrimSpace(serverURL) == "" {
				continue
			}
			if parsed, parseErr := url.Parse(serverURL); parseErr == nil && strings.TrimSpace(parsed.Path) != "" {
				serverPath = normalizePrefix(parsed.Path)
				break
			}
		}
	}

	index := &openAPIIndex{
		ServerURLPath: serverPath,
		Operations:    map[string]operation{},
	}

	paths, ok := document["paths"].(map[string]any)
	if !ok {
		return nil, errors.New("openapi document has no [paths] object")
	}

	for pathKey, rawPathValue := range paths {
		pathObj, ok := rawPathValue.(map[string]any)
		if !ok {
			continue
		}

		for _, method := range []string{http.MethodGet, http.MethodPost, http.MethodPut, http.MethodPatch, http.MethodDelete} {
			methodEntry, ok := pathObj[strings.ToLower(method)].(map[string]any)
			if !ok {
				continue
			}

			opID, _ := methodEntry["operationId"].(string)
			if strings.TrimSpace(opID) == "" {
				continue
			}

			tags := []string{}
			if rawTags, ok := methodEntry["tags"].([]any); ok {
				for _, tag := range rawTags {
					tagString, ok := tag.(string)
					if ok && strings.TrimSpace(tagString) != "" {
						tags = append(tags, tagString)
					}
				}
			}

			summary, _ := methodEntry["summary"].(string)

			index.Operations[opID] = operation{
				OperationID: opID,
				Method:      method,
				Path:        joinURLPath(serverPath, pathKey),
				Tags:        tags,
				Summary:     summary,
			}
		}
	}

	if len(index.Operations) == 0 {
		return nil, errors.New("openapi operation index is empty")
	}

	return index, nil
}

func (s *mcpServer) apiCall(
	ctx context.Context,
	method string,
	path string,
	query map[string]any,
	body map[string]any,
	headers map[string]string,
	token string,
) (map[string]any, error) {
	if !strings.HasPrefix(path, "/api/") {
		return nil, fmt.Errorf("path [%s] is outside api namespace", path)
	}

	fullURL, err := appendPathAndQuery(s.cfg.APIBaseURL, path, query)
	if err != nil {
		return nil, err
	}

	var requestBody io.Reader
	hasBody := body != nil
	if hasBody {
		encoded, err := json.Marshal(body)
		if err != nil {
			return nil, err
		}
		requestBody = bytes.NewReader(encoded)
	}

	request, err := http.NewRequestWithContext(ctx, method, fullURL, requestBody)
	if err != nil {
		return nil, err
	}

	request.Header.Set("Accept", "application/json")
	request.Header.Set("User-Agent", "PymeSec-MCP-Go/1.0")
	if hasBody {
		request.Header.Set("Content-Type", "application/json")
	}
	if strings.TrimSpace(token) != "" {
		request.Header.Set("Authorization", "Bearer "+strings.TrimSpace(token))
	}
	for key, value := range headers {
		if strings.TrimSpace(key) == "" {
			continue
		}
		request.Header.Set(key, value)
	}

	response, err := s.client.Do(request)
	if err != nil {
		return nil, err
	}
	defer response.Body.Close()

	rawBody, err := io.ReadAll(io.LimitReader(response.Body, 10*1024*1024))
	if err != nil {
		return nil, err
	}

	decodedBody := decodeMaybeJSON(rawBody)

	responseHeaders := map[string]string{}
	for _, key := range []string{
		"Content-Type",
		"X-PymeSec-OpenApi-Version",
		"X-PymeSec-OpenApi-Compat",
		"Link",
	} {
		if value := strings.TrimSpace(response.Header.Get(key)); value != "" {
			responseHeaders[strings.ToLower(key)] = value
		}
	}

	return map[string]any{
		"status":  response.StatusCode,
		"headers": responseHeaders,
		"body":    decodedBody,
	}, nil
}

func appendPathAndQuery(baseURL string, path string, query map[string]any) (string, error) {
	parsed, err := url.Parse(strings.TrimSpace(baseURL))
	if err != nil {
		return "", err
	}

	parsed.Path = joinURLPath(parsed.Path, path)
	values := parsed.Query()
	for key, value := range query {
		if key == "" || value == nil {
			continue
		}

		switch typed := value.(type) {
		case string:
			values.Set(key, typed)
		case []string:
			for _, item := range typed {
				values.Add(key+"[]", item)
			}
		case []any:
			for _, item := range typed {
				values.Add(key+"[]", fmt.Sprintf("%v", item))
			}
		default:
			values.Set(key, fmt.Sprintf("%v", typed))
		}
	}
	parsed.RawQuery = values.Encode()

	return parsed.String(), nil
}

func resolvePathTemplate(template string, params map[string]any) (string, error) {
	missing := []string{}
	resolved := pathParamPattern.ReplaceAllStringFunc(template, func(segment string) string {
		match := pathParamPattern.FindStringSubmatch(segment)
		if len(match) != 2 {
			return segment
		}

		key := match[1]
		value, ok := params[key]
		if !ok || value == nil {
			missing = append(missing, key)
			return segment
		}

		return url.PathEscape(fmt.Sprintf("%v", value))
	})

	if len(missing) > 0 {
		return "", fmt.Errorf("missing path params: %s", strings.Join(missing, ", "))
	}

	return resolved, nil
}

func decodeMaybeJSON(raw []byte) any {
	trimmed := strings.TrimSpace(string(raw))
	if trimmed == "" {
		return nil
	}

	var parsed any
	if err := json.Unmarshal(raw, &parsed); err == nil {
		return parsed
	}

	return trimmed
}

func mapArg(input map[string]any, key string) map[string]any {
	value, ok := input[key]
	if !ok || value == nil {
		return nil
	}

	typed, ok := value.(map[string]any)
	if !ok {
		return nil
	}

	return typed
}

func stringMapArg(input map[string]any, key string) map[string]string {
	value, ok := input[key]
	if !ok || value == nil {
		return nil
	}

	source, ok := value.(map[string]any)
	if !ok {
		return nil
	}

	target := map[string]string{}
	for sourceKey, sourceValue := range source {
		if sourceKey == "" {
			continue
		}
		target[sourceKey] = fmt.Sprintf("%v", sourceValue)
	}

	if len(target) == 0 {
		return nil
	}

	return target
}

func stringSliceArg(input map[string]any, key string) []string {
	value, ok := input[key]
	if !ok || value == nil {
		return nil
	}

	switch typed := value.(type) {
	case []string:
		return typed
	case []any:
		out := []string{}
		for _, entry := range typed {
			out = append(out, fmt.Sprintf("%v", entry))
		}
		return out
	default:
		return nil
	}
}

func stringArg(input map[string]any, key string) string {
	value, ok := input[key]
	if !ok || value == nil {
		return ""
	}

	switch typed := value.(type) {
	case string:
		return strings.TrimSpace(typed)
	default:
		return strings.TrimSpace(fmt.Sprintf("%v", typed))
	}
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return strings.TrimSpace(value)
		}
	}

	return ""
}

func normalizePrefix(prefix string) string {
	trimmed := strings.TrimSpace(prefix)
	if trimmed == "" {
		return defaultAPIPrefix
	}

	if !strings.HasPrefix(trimmed, "/") {
		trimmed = "/" + trimmed
	}

	return "/" + strings.Trim(strings.TrimSpace(trimmed), "/")
}

func joinURLPath(parts ...string) string {
	cleaned := make([]string, 0, len(parts))
	for _, part := range parts {
		if strings.TrimSpace(part) == "" {
			continue
		}
		cleaned = append(cleaned, strings.Trim(part, "/"))
	}

	if len(cleaned) == 0 {
		return "/"
	}

	return "/" + strings.Join(cleaned, "/")
}

func parseBoolEnv(key string, fallback bool) bool {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return fallback
	}

	value, err := strconv.ParseBool(raw)
	if err != nil {
		return fallback
	}

	return value
}

func parseDurationEnv(key string, fallback time.Duration) time.Duration {
	raw := strings.TrimSpace(os.Getenv(key))
	if raw == "" {
		return fallback
	}

	parsed, err := time.ParseDuration(raw)
	if err != nil {
		return fallback
	}

	return parsed
}
