#!/usr/bin/env bash
# Gate permanente de UI/UX (feature 016 · T038 / FR-015).
#
# Roda:
#   1. Scanner de chaves i18n cruas (G14) — sempre (estático, sem browser).
#   2. Scanner de diálogos nativos confirm/prompt/alert (G13) — sempre.
#   3. Gate de invariantes Playwright (G1/G3/G5/G10/G11/G12) — só se
#      PLAYWRIGHT_BASE_URL estiver definido (exige app no ar + tenant seedado).
#
# Uso:
#   bash tests/ux/gate.sh
#   PLAYWRIGHT_BASE_URL=https://clinica-alfa.paciente-360.com bash tests/ux/gate.sh
set -euo pipefail
cd "$(dirname "$0")/../.."

echo "── [1/3] Scanner i18n (G14) ───────────────────────────────"
node tests/ux/scan-i18n-keys.mjs

echo "── [2/3] Scanner diálogos nativos (G13) ───────────────────"
node tests/ux/scan-native-dialogs.mjs

if [ -n "${PLAYWRIGHT_BASE_URL:-}" ]; then
    echo "── [3/3] Gate de invariantes Playwright (G1/G3/G5/G10-12) ─"
    UX_GATE=1 npx playwright test --config=playwright.ux.config.ts invariants.gate
else
    echo "── [3/3] Playwright gate PULADO (defina PLAYWRIGHT_BASE_URL) ─"
fi

echo "✅ UX gate OK"
