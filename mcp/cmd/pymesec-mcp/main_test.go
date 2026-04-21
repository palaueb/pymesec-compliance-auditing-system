package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestResolvePathTemplate(t *testing.T) {
	resolved, err := resolvePathTemplate("/api/v1/assets/{assetId}/owners/{ownerId}", map[string]any{
		"assetId": "asset-1",
		"ownerId": "actor a",
	})
	if err != nil {
		t.Fatalf("resolvePathTemplate returned error: %v", err)
	}

	if resolved != "/api/v1/assets/asset-1/owners/actor%20a" {
		t.Fatalf("unexpected resolved path: %s", resolved)
	}
}

func TestFetchOpenAPIBuildsOperationIndex(t *testing.T) {
	openapiPayload := `{
	  "openapi": "3.1.0",
	  "servers": [{"url": "/api/v1"}],
	  "paths": {
	    "/assets": {
	      "get": {
	        "operationId": "assetCatalogListAssets",
	        "tags": ["asset-catalog"],
	        "summary": "List assets"
	      }
	    },
	    "/assets/{assetId}": {
	      "patch": {
	        "operationId": "assetCatalogUpdateAsset",
	        "tags": ["asset-catalog"],
	        "summary": "Update asset"
	      }
	    }
	  }
	}`

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(openapiPayload))
	}))
	defer server.Close()

	s := &mcpServer{
		cfg: config{
			APIBaseURL: server.URL,
			APIPrefix:  "/api/v1",
			OpenAPIURL: server.URL + "/openapi/v1.json",
		},
		client: &http.Client{
			Timeout: 5 * time.Second,
		},
	}

	index, err := s.fetchOpenAPI(context.Background(), "")
	if err != nil {
		t.Fatalf("fetchOpenAPI returned error: %v", err)
	}

	if len(index.Operations) != 2 {
		t.Fatalf("expected 2 operations, got %d", len(index.Operations))
	}

	op := index.Operations["assetCatalogUpdateAsset"]
	if op.Method != http.MethodPatch {
		t.Fatalf("expected PATCH method, got %s", op.Method)
	}

	if op.Path != "/api/v1/assets/{assetId}" {
		t.Fatalf("unexpected operation path: %s", op.Path)
	}
}

func TestWriteJSONRPCMessageUsesMinimalMCPStdioHeaders(t *testing.T) {
	var output bytes.Buffer
	writer := bufio.NewWriter(&output)

	if err := writeJSONRPCMessage(writer, jsonRPCResponse{
		JSONRPC: "2.0",
		ID:      1,
		Result: map[string]any{
			"ok": true,
		},
	}, messageFramingHeader); err != nil {
		t.Fatalf("writeJSONRPCMessage returned error: %v", err)
	}

	serialized := output.String()
	if !strings.HasPrefix(serialized, "Content-Length: ") {
		t.Fatalf("response is missing Content-Length header: %q", serialized)
	}
	if strings.Contains(serialized, "Content-Type:") {
		t.Fatalf("response should not include Content-Type header: %q", serialized)
	}
	if !strings.Contains(serialized, "\r\n\r\n") {
		t.Fatalf("response is missing MCP stdio header separator: %q", serialized)
	}
}

func TestWriteJSONRPCMessagePreservesJSONLFraming(t *testing.T) {
	var output bytes.Buffer
	writer := bufio.NewWriter(&output)

	if err := writeJSONRPCMessage(writer, jsonRPCResponse{
		JSONRPC: "2.0",
		ID:      1,
		Result: map[string]any{
			"ok": true,
		},
	}, messageFramingJSONL); err != nil {
		t.Fatalf("writeJSONRPCMessage returned error: %v", err)
	}

	serialized := output.String()
	if strings.HasPrefix(serialized, "Content-Length:") {
		t.Fatalf("jsonl response should not include MCP headers: %q", serialized)
	}
	if !strings.HasSuffix(serialized, "\n") {
		t.Fatalf("jsonl response should end with newline: %q", serialized)
	}
	if !strings.HasPrefix(serialized, "{\"jsonrpc\":\"2.0\"") {
		t.Fatalf("jsonl response should start with JSON object: %q", serialized)
	}
}

func TestInitializeAdvertisesResourceCapabilities(t *testing.T) {
	s := &mcpServer{
		cfg: config{
			ServerName: "PymeSec MCP Server",
			ServerVer:  "1.0.0",
		},
	}

	resp, hasResponse := s.handle(jsonRPCRequest{
		JSONRPC: "2.0",
		ID:      1,
		Method:  "initialize",
		Params: map[string]any{
			"protocolVersion": "2026-01-01",
		},
	})

	if !hasResponse {
		t.Fatal("initialize returned no response")
	}
	if resp.Error != nil {
		t.Fatalf("initialize returned error: %v", resp.Error)
	}

	result, ok := resp.Result.(map[string]any)
	if !ok {
		t.Fatalf("unexpected initialize result shape: %#v", resp.Result)
	}
	if result["protocolVersion"] != "2026-01-01" {
		t.Fatalf("initialize did not echo requested protocol version: %#v", result["protocolVersion"])
	}
	capabilities, ok := result["capabilities"].(map[string]any)
	if !ok {
		t.Fatalf("initialize result missing capabilities: %#v", result)
	}
	resources, ok := capabilities["resources"].(map[string]any)
	if !ok {
		t.Fatalf("initialize capabilities missing resources: %#v", capabilities)
	}
	if resources["listChanged"] != false {
		t.Fatalf("expected resources.listChanged false, got %#v", resources["listChanged"])
	}
}

func TestResourcesListAndTemplatesList(t *testing.T) {
	s := &mcpServer{}

	resourcesResp, hasResponse := s.handle(jsonRPCRequest{
		JSONRPC: "2.0",
		ID:      1,
		Method:  "resources/list",
	})
	if !hasResponse {
		t.Fatal("resources/list returned no response")
	}
	if resourcesResp.Error != nil {
		t.Fatalf("resources/list returned error: %v", resourcesResp.Error)
	}

	resourcesResult, ok := resourcesResp.Result.(map[string]any)
	if !ok {
		t.Fatalf("unexpected resources/list result shape: %#v", resourcesResp.Result)
	}
	resources, ok := resourcesResult["resources"].([]map[string]any)
	if !ok {
		t.Fatalf("resources/list missing resources: %#v", resourcesResult)
	}
	if len(resources) != 3 {
		t.Fatalf("expected 3 resources, got %d", len(resources))
	}

	templatesResp, hasResponse := s.handle(jsonRPCRequest{
		JSONRPC: "2.0",
		ID:      2,
		Method:  "resources/templates/list",
	})
	if !hasResponse {
		t.Fatal("resources/templates/list returned no response")
	}
	if templatesResp.Error != nil {
		t.Fatalf("resources/templates/list returned error: %v", templatesResp.Error)
	}

	templatesResult, ok := templatesResp.Result.(map[string]any)
	if !ok {
		t.Fatalf("unexpected resources/templates/list result shape: %#v", templatesResp.Result)
	}
	templates, ok := templatesResult["resourceTemplates"].([]map[string]any)
	if !ok {
		t.Fatalf("resources/templates/list missing resourceTemplates: %#v", templatesResult)
	}
	if len(templates) != 1 {
		t.Fatalf("expected 1 resource template, got %d", len(templates))
	}
	if templates[0]["uriTemplate"] != resourceOperationURITemplate {
		t.Fatalf("unexpected resource template: %#v", templates[0])
	}
}

func TestResourcesReadOperationResource(t *testing.T) {
	openapiPayload := `{
	  "openapi": "3.1.0",
	  "servers": [{"url": "/api/v1"}],
	  "paths": {
	    "/assets": {
	      "get": {
	        "operationId": "assetCatalogListAssets",
	        "tags": ["asset-catalog"],
	        "summary": "List assets"
	      }
	    }
	  }
	}`

	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(openapiPayload))
	}))
	defer server.Close()

	s := &mcpServer{
		cfg: config{
			APIBaseURL: server.URL,
			APIPrefix:  "/api/v1",
			OpenAPIURL: server.URL + "/openapi/v1.json",
		},
		client: &http.Client{
			Timeout: 5 * time.Second,
		},
	}

	resp, hasResponse := s.handle(jsonRPCRequest{
		JSONRPC: "2.0",
		ID:      1,
		Method:  "resources/read",
		Params: map[string]any{
			"uri": resourceOperationURIPrefix + "assetCatalogListAssets",
		},
	})
	if !hasResponse {
		t.Fatal("resources/read returned no response")
	}
	if resp.Error != nil {
		t.Fatalf("resources/read returned error: %v", resp.Error)
	}

	result, ok := resp.Result.(map[string]any)
	if !ok {
		t.Fatalf("unexpected resources/read result shape: %#v", resp.Result)
	}
	contents, ok := result["contents"].([]map[string]any)
	if !ok {
		t.Fatalf("resources/read missing contents: %#v", result)
	}
	if len(contents) != 1 {
		t.Fatalf("expected 1 content item, got %d", len(contents))
	}

	var decoded map[string]any
	if err := json.Unmarshal([]byte(contents[0]["text"].(string)), &decoded); err != nil {
		t.Fatalf("resource content is not json: %v", err)
	}

	operation, ok := decoded["operation"].(map[string]any)
	if !ok {
		t.Fatalf("resource content missing operation: %#v", decoded)
	}
	if operation["operation_id"] != "assetCatalogListAssets" {
		t.Fatalf("unexpected operation_id: %#v", operation["operation_id"])
	}
}
