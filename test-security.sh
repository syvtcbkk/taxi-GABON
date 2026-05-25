#!/bin/bash
# test-security.sh - Tests rapides des améliorations de sécurité

echo "🔒 Tests de sécurité - Taxi Gabon"
echo "=================================="

BASE_URL="http://localhost"
SESSION_COOKIE=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo ""
echo -e "${YELLOW}Test 1: API sans authentification${NC}"
curl -s -X POST "$BASE_URL/api/book-ride.php" \
  -H "Content-Type: application/json" \
  -d '{}' | grep -q "Non authentifi" && echo -e "${GREEN}✓ PASS${NC}" || echo -e "${RED}✗ FAIL${NC}"

echo ""
echo -e "${YELLOW}Test 2: Validation email invalide${NC}"
curl -s -X POST "$BASE_URL/actions/auth-register.php" \
  -d "first_name=Test&last_name=User&email=invalid&phone=+12345&password=password123&role=passenger&csrf_token=fake" | \
  grep -q "invalide" && echo -e "${GREEN}✓ PASS${NC}" || echo -e "${RED}✗ FAIL${NC}"

echo ""
echo -e "${YELLOW}Test 3: Mot de passe trop court${NC}"
curl -s -X POST "$BASE_URL/actions/auth-register.php" \
  -d "first_name=Test&last_name=User&email=test@example.com&phone=+212612345678&password=short&role=passenger&csrf_token=fake" | \
  grep -q "au moins 8" && echo -e "${GREEN}✓ PASS${NC}" || echo -e "${RED}✗ FAIL${NC}"

echo ""
echo -e "${YELLOW}Test 4: Rate limiting${NC}"
for i in {1..6}; do
  curl -s -X POST "$BASE_URL/api/book-ride.php" \
    -H "Content-Type: application/json" \
    -d '{}' > /dev/null
done
curl -s -X POST "$BASE_URL/api/book-ride.php" \
  -H "Content-Type: application/json" \
  -d '{}' | grep -q "429\|Trop" && echo -e "${GREEN}✓ PASS${NC}" || echo -e "${RED}✗ FAIL${NC}"

echo ""
echo -e "${YELLOW}Test 5: GPS invalides${NC}"
curl -s -X POST "$BASE_URL/api/book-ride.php" \
  -H "Content-Type: application/json" \
  -d '{"origin_lat":91,"origin_lng":0}' | grep -q "invalide" && echo -e "${GREEN}✓ PASS${NC}" || echo -e "${RED}✗ FAIL${NC}"

echo ""
echo -e "${YELLOW}Tests complétés!${NC}"
