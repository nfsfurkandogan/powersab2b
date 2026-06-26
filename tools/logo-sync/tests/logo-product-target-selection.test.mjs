import assert from "node:assert/strict";
import test from "node:test";

import {
  hasProductTargetSelection,
  parseProductTargetCodes,
  parseProductTargetRefs,
} from "../logo-product-target-selection.mjs";

test("parseProductTargetRefs keeps positive numeric refs once", () => {
  assert.deepEqual(parseProductTargetRefs("13941, 9123 13941;0;-5;abc;44"), [
    13941,
    9123,
    44,
  ]);
});

test("parseProductTargetCodes keeps spaced product codes intact", () => {
  assert.deepEqual(parseProductTargetCodes("CS 0040, 70 800;0451103336\nCS 0040"), [
    "CS 0040",
    "70 800",
    "0451103336",
  ]);
});

test("hasProductTargetSelection detects either refs or codes", () => {
  assert.equal(hasProductTargetSelection({ targetRefs: [], targetCodes: [] }), false);
  assert.equal(hasProductTargetSelection({ targetRefs: [13941], targetCodes: [] }), true);
  assert.equal(hasProductTargetSelection({ targetRefs: [], targetCodes: ["CS 0040"] }), true);
});
