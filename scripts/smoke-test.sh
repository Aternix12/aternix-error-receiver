#!/usr/bin/env bash
URL="https://errors.aternix.com/api/error-reports"
TOK="1e96750d366f0e3d38b70bb588b9c0098be9c219b1b4be0a"
PASS=0; FAIL=0
check(){ local name="$1" got="$2" expected="$3"
  if [ "$got" = "$expected" ]; then PASS=$((PASS+1)); printf "PASS  %-55s (HTTP %s)\n" "$name" "$got";
  else FAIL=$((FAIL+1)); printf "FAIL  %-55s expected %s got %s\n" "$name" "$expected" "$got"; fi
}
post(){ curl -s --max-time 20 -o /dev/null -w "%{http_code}" -X POST "$URL" -H "Content-Type: application/json" -H "Accept: application/json" "$@"; }
postjson(){ curl -s --max-time 20 -X POST "$URL" -H "Content-Type: application/json" -H "Accept: application/json" "$@"; }
getj(){ curl -s --max-time 20 "$@"; }

echo "=== AUTH ==="
check "health GET /api"                  "$(curl -s --max-time 20 -o /dev/null -w '%{http_code}' https://errors.aternix.com/api)" "200"
check "POST no token -> 401"             "$(post -d '{"summary":"x"}')" "401"
check "POST wrong token -> 401"          "$(post -H 'X-Report-Token: WRONG' -d '{"summary":"x"}')" "401"
check "POST right token -> 201"          "$(post -H "X-Report-Token: $TOK" -d '{"project":"test-suite","summary":"auth ok"}')" "201"
check "GET no token -> 401"              "$(curl -s --max-time 20 -o /dev/null -w '%{http_code}' "$URL")" "401"
S=$(curl -s --max-time 20 -o /dev/null -w "%{http_code}" -H "X-Report-Token: $TOK" "$URL"); check "GET right token -> 200" "$S" "200"

# Helper: POST via stdin (avoids ARG_MAX for big payloads)
post_stdin(){ python3 -c "import sys,json;sys.stdout.write(json.dumps($1))" | curl -s --max-time 25 -o /dev/null -w "%{http_code}" -X POST "$URL" -H "Content-Type: application/json" -H "Accept: application/json" -H "X-Report-Token: $TOK" --data-binary @-; }

echo
echo "=== VALIDATION (limits in controller) ==="
check "invalid report_type -> 422"       "$(post -H "X-Report-Token: $TOK" -d '{"project":"test-suite","report_type":"bogus"}')" "422"
check "log_tail > 500000 chars -> 422"   "$(post_stdin "{'project':'test-suite','log_tail':'x'*500001}")" "422"
check "frontend_report > 100000 -> 422"  "$(post_stdin "{'project':'test-suite','frontend_report':'x'*100001}")" "422"
check "project > 100 chars -> 422"       "$(post_stdin "{'project':'x'*101,'summary':'p'}")" "422"
BIG_NOTE=$(python3 -c "print('x'*5001)")
check "user_note > 5000 chars -> 422"    "$(post -H "X-Report-Token: $TOK" -d "{\"project\":\"test-suite\",\"user_note\":\"$BIG_NOTE\"}")" "422"
BIG_SUM=$(python3 -c "print('x'*501)")
check "summary > 500 chars -> 422"       "$(post -H "X-Report-Token: $TOK" -d "{\"project\":\"test-suite\",\"summary\":\"$BIG_SUM\"}")" "422"

echo
echo "=== STORAGE + READBACK ==="
RID=$(postjson -H "X-Report-Token: $TOK" -d '{"project":"test-suite-a","app_version":"9.9.9","platform":"linux test","hostname":"BOX","report_type":"manual","summary":"readback test","user_note":"hi","frontend_report":"FE","log_tail":"LT"}' | python3 -c "import sys,json;print(json.load(sys.stdin)['data']['id'])")
echo "stored id=$RID"
ROW=$(getj -H "X-Report-Token: $TOK" "$URL?project=test-suite-a" | python3 -c "import sys,json;d=json.load(sys.stdin)['data'][0];print(d['project'],'|',d['app_version'],'|',d['report_type'],'|',d['summary'],'|',d['frontend_report'],'|',d['log_tail'])")
[ "$ROW" = "test-suite-a | 9.9.9 | manual | readback test | FE | LT" ] && { PASS=$((PASS+1)); echo "PASS  all fields persisted correctly"; } || { FAIL=$((FAIL+1)); echo "FAIL  readback mismatch: $ROW"; }

echo
echo "=== DEFAULTS ==="
postjson -H "X-Report-Token: $TOK" -d '{"summary":"no project / no type"}' >/dev/null
DEF=$(getj -H "X-Report-Token: $TOK" "$URL?project=unknown" | python3 -c "import sys,json;d=json.load(sys.stdin)['data'][0];print(d['project'],'|',d['report_type'])")
[ "$DEF" = "unknown | auto" ] && { PASS=$((PASS+1)); echo "PASS  defaults: project='unknown', report_type='auto'"; } || { FAIL=$((FAIL+1)); echo "FAIL  defaults wrong: $DEF"; }

echo
echo "=== MULTI-PROJECT FILTERING ==="
postjson -H "X-Report-Token: $TOK" -d '{"project":"test-suite-b","summary":"only B"}' >/dev/null
CNT_A=$(getj -H "X-Report-Token: $TOK" "$URL?project=test-suite-a" | python3 -c "import sys,json;print(len(json.load(sys.stdin)['data']))")
CNT_B=$(getj -H "X-Report-Token: $TOK" "$URL?project=test-suite-b" | python3 -c "import sys,json;print(len(json.load(sys.stdin)['data']))")
[ "$CNT_A" = "1" ] && [ "$CNT_B" = "1" ] && { PASS=$((PASS+1)); echo "PASS  project filter isolates rows (A=$CNT_A B=$CNT_B)"; } || { FAIL=$((FAIL+1)); echo "FAIL  A=$CNT_A B=$CNT_B"; }

echo
echo "=== THROTTLE (60/min) ==="
# Fire 70 fast POSTs and count 429s
RES=$(for i in $(seq 1 70); do curl -s --max-time 5 -o /dev/null -w "%{http_code}\n" -X POST "$URL" -H "Content-Type: application/json" -H "X-Report-Token: $TOK" -d "{\"project\":\"throttle-test\",\"summary\":\"burst $i\"}" & done; wait)
N201=$(echo "$RES" | grep -c 201)
N429=$(echo "$RES" | grep -c 429)
echo "  fired 70 -> 201=$N201, 429=$N429"
if [ "$N429" -gt 0 ] && [ "$N201" -le 60 ]; then PASS=$((PASS+1)); echo "PASS  throttle engaged (429 >0 and 201 <=60)"; else FAIL=$((FAIL+1)); echo "FAIL  throttle didn't engage as expected"; fi

echo
echo "=== TOTAL: $PASS passed, $FAIL failed ==="
exit $FAIL
