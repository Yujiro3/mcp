#!/usr/bin/env sh
set -eu

BASE_URL="${MCP_BASE_URL:-http://localhost:8080/mcp}"
WORK_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
TMP_DIR="${TMPDIR:-/tmp}/mcp-sample-$$"

mkdir -p "$TMP_DIR"
trap 'rm -rf "$TMP_DIR"' EXIT INT TERM

log() {
  printf '%s\n' "$*"
}

fail_with_response() {
  step="$1"
  body_file="$2"
  header_file="$3"
  log "ERROR: ${step} failed."
  log "Headers:"
  cat "$header_file"
  log "Body:"
  cat "$body_file"
  exit 1
}

assert_http_2xx() {
  step="$1"
  header_file="$2"
  status_code="$(awk 'NR==1 {print $2}' "$header_file")"
  case "$status_code" in
    2*) ;;
    *)
      log "ERROR: ${step} returned non-2xx status: ${status_code:-unknown}"
      log "Headers:"
      cat "$header_file"
      exit 1
      ;;
  esac
}

assert_no_jsonrpc_error() {
  step="$1"
  body_file="$2"
  header_file="$3"
  if grep -q '"error"[[:space:]]*:' "$body_file"; then
    fail_with_response "$step" "$body_file" "$header_file"
  fi
}

assert_tool_exists() {
  step="$1"
  body_file="$2"
  header_file="$3"
  tool_name="$4"
  normalized="$(tr -d ' \n\r\t' < "$body_file")"

  case "$normalized" in
    *"\"name\":\"${tool_name}\""*) ;;
    *)
      log "ERROR: ${step} result does not include tool: ${tool_name}"
      fail_with_response "$step" "$body_file" "$header_file"
      ;;
  esac
}

post_json() {
  file_path="$1"
  out_body="$2"
  out_header="$3"
  session_id="${4:-}"

  if [ -n "$session_id" ]; then
    curl -sS -D "$out_header" -o "$out_body" \
      -X POST "$BASE_URL" \
      -H "Content-Type: application/json" \
      -H "Mcp-Protocol-Version: 2024-11-05" \
      -H "Mcp-Session-Id: $session_id" \
      --data-binary "@$file_path"
  else
    curl -sS -D "$out_header" -o "$out_body" \
      -X POST "$BASE_URL" \
      -H "Content-Type: application/json" \
      -H "Mcp-Protocol-Version: 2024-11-05" \
      --data-binary "@$file_path"
  fi
}

extract_session_id() {
  header_file="$1"
  awk 'BEGIN{IGNORECASE=1} /^Mcp-Session-Id:/ {gsub("\r", "", $2); print $2}' "$header_file" | head -n 1
}

extract_transform_id() {
  body_file="$1"
  sed -n 's/.*"id"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$body_file" | head -n 1
}

log "[1/4] initialize"
post_json "$WORK_DIR/initialize.json" "$TMP_DIR/init.json" "$TMP_DIR/init.headers"
assert_http_2xx "initialize" "$TMP_DIR/init.headers"
assert_no_jsonrpc_error "initialize" "$TMP_DIR/init.json" "$TMP_DIR/init.headers"
SESSION_ID="$(extract_session_id "$TMP_DIR/init.headers")"
if [ -z "$SESSION_ID" ]; then
  log "ERROR: Mcp-Session-Id was not found in initialize response headers."
  log "Response headers:"
  cat "$TMP_DIR/init.headers"
  exit 1
fi
log "Session: $SESSION_ID"

log "[2/4] tools/list"
post_json "$WORK_DIR/tools_list.json" "$TMP_DIR/list.json" "$TMP_DIR/list.headers" "$SESSION_ID"
assert_http_2xx "tools/list" "$TMP_DIR/list.headers"
assert_no_jsonrpc_error "tools/list" "$TMP_DIR/list.json" "$TMP_DIR/list.headers"
assert_tool_exists "tools/list" "$TMP_DIR/list.json" "$TMP_DIR/list.headers" "transforms"
assert_tool_exists "tools/list" "$TMP_DIR/list.json" "$TMP_DIR/list.headers" "preview"
assert_tool_exists "tools/list" "$TMP_DIR/list.json" "$TMP_DIR/list.headers" "download"
cat "$TMP_DIR/list.json"
printf '\n'

log "[3/4] tools/call transforms"
post_json "$WORK_DIR/tools_call_transforms.json" "$TMP_DIR/transforms.json" "$TMP_DIR/transforms.headers" "$SESSION_ID"
assert_http_2xx "tools/call transforms" "$TMP_DIR/transforms.headers"
assert_no_jsonrpc_error "tools/call transforms" "$TMP_DIR/transforms.json" "$TMP_DIR/transforms.headers"
cat "$TMP_DIR/transforms.json"
printf '\n'

TRANSFORM_ID="$(extract_transform_id "$TMP_DIR/transforms.json")"
if [ -z "$TRANSFORM_ID" ]; then
  log "ERROR: transform id was not found in transforms response body."
  exit 1
fi
log "Transform ID: $TRANSFORM_ID"

log "[4/5] tools/call preview"
PREVIEW_PAYLOAD="$TMP_DIR/tools_call_preview.generated.json"
sed "s/transforms:REPLACE_WITH_ID/$TRANSFORM_ID/g" "$WORK_DIR/tools_call_preview.json" > "$PREVIEW_PAYLOAD"
post_json "$PREVIEW_PAYLOAD" "$TMP_DIR/preview.json" "$TMP_DIR/preview.headers" "$SESSION_ID"
assert_http_2xx "tools/call preview" "$TMP_DIR/preview.headers"
assert_no_jsonrpc_error "tools/call preview" "$TMP_DIR/preview.json" "$TMP_DIR/preview.headers"
cat "$TMP_DIR/preview.json"
printf '\n'

log "[5/5] tools/call download"
DOWNLOAD_PAYLOAD="$TMP_DIR/tools_call_download.generated.json"
sed "s/transforms:REPLACE_WITH_ID/$TRANSFORM_ID/g" "$WORK_DIR/tools_call_download.json" > "$DOWNLOAD_PAYLOAD"
post_json "$DOWNLOAD_PAYLOAD" "$TMP_DIR/download.json" "$TMP_DIR/download.headers" "$SESSION_ID"
assert_http_2xx "tools/call download" "$TMP_DIR/download.headers"
assert_no_jsonrpc_error "tools/call download" "$TMP_DIR/download.json" "$TMP_DIR/download.headers"
cat "$TMP_DIR/download.json"
printf '\n'

log "Done"
