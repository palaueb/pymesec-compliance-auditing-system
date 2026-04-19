package main

import (
	"context"
	"net/http"
	"net/http/httptest"
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
