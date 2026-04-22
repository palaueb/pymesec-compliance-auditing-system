package main

import "testing"

func TestJSONEqualIgnoringVolatileFieldsIgnoresRequestIDs(t *testing.T) {
	left := map[string]any{
		"data": map[string]any{
			"server": map[string]any{
				"id": "pymesec-official-mcp",
			},
		},
		"meta": map[string]any{
			"request_id": "req-call-operation",
		},
	}

	right := map[string]any{
		"data": map[string]any{
			"server": map[string]any{
				"id": "pymesec-official-mcp",
			},
		},
		"meta": map[string]any{
			"request_id": "req-api-request",
		},
	}

	if !jsonEqualIgnoringVolatileFields(left, right) {
		t.Fatal("expected API bodies with only different request IDs to be equivalent")
	}
}

func TestJSONEqualIgnoringVolatileFieldsDetectsSemanticMismatch(t *testing.T) {
	left := map[string]any{
		"data": map[string]any{
			"server": map[string]any{
				"id": "pymesec-official-mcp",
			},
		},
		"meta": map[string]any{
			"request_id": "req-call-operation",
		},
	}

	right := map[string]any{
		"data": map[string]any{
			"server": map[string]any{
				"id": "different-server",
			},
		},
		"meta": map[string]any{
			"request_id": "req-api-request",
		},
	}

	if jsonEqualIgnoringVolatileFields(left, right) {
		t.Fatal("expected API bodies with different data to remain different")
	}
}
