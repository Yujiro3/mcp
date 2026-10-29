MCP HTTP sample requests

Endpoint:
- http://localhost:8080/mcp

Flow:
1) Send initialize.json and get Mcp-Session-Id from response header
2) Send tools_list.json with Mcp-Session-Id
3) Send tools_call_transforms.json with Mcp-Session-Id, then copy returned id
4) Replace id in tools_call_preview.json and send it with Mcp-Session-Id

Quick run script:
- opt/sample/run_mcp_flow.sh
- Script validates HTTP status and JSON-RPC error on each step.

Usage:
chmod +x opt/sample/run_mcp_flow.sh
./opt/sample/run_mcp_flow.sh

Optional endpoint override:
MCP_BASE_URL=http://localhost:8080/mcp ./opt/sample/run_mcp_flow.sh

Example with curl (run from project root):

# 1) initialize (save headers)
curl -sS -D /tmp/mcp_headers.txt -o /tmp/mcp_init.json \
  -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Mcp-Protocol-Version: 2024-11-05" \
  --data-binary @opt/sample/initialize.json

# extract session id
SESSION_ID=$(awk 'BEGIN{IGNORECASE=1} /^Mcp-Session-Id:/ {gsub("\r", "", $2); print $2}' /tmp/mcp_headers.txt)

# 2) list tools
curl -sS -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Mcp-Protocol-Version: 2024-11-05" \
  -H "Mcp-Session-Id: ${SESSION_ID}" \
  --data-binary @opt/sample/tools_list.json

# 3) register transforms
curl -sS -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Mcp-Protocol-Version: 2024-11-05" \
  -H "Mcp-Session-Id: ${SESSION_ID}" \
  --data-binary @opt/sample/tools_call_transforms.json

# 4) preview (replace id in JSON before sending)
curl -sS -X POST http://localhost:8080/mcp \
  -H "Content-Type: application/json" \
  -H "Mcp-Protocol-Version: 2024-11-05" \
  -H "Mcp-Session-Id: ${SESSION_ID}" \
  --data-binary @opt/sample/tools_call_preview.json
