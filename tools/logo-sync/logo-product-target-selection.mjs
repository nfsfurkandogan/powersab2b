function uniquePush(items, value, key = value) {
  if (!value || items.seen.has(key)) {
    return;
  }

  items.seen.add(key);
  items.values.push(value);
}

export function parseProductTargetRefs(value) {
  if (!value) {
    return [];
  }

  const refs = { seen: new Set(), values: [] };

  String(value)
    .split(/[,\s;]+/)
    .map((part) => Number.parseInt(part.trim(), 10))
    .filter((ref) => Number.isFinite(ref) && ref > 0)
    .forEach((ref) => uniquePush(refs, ref, String(ref)));

  return refs.values;
}

export function parseProductTargetCodes(value) {
  if (!value) {
    return [];
  }

  const codes = { seen: new Set(), values: [] };

  String(value)
    .split(/[,\n;]+/)
    .map((part) => part.trim())
    .filter(Boolean)
    .forEach((code) => uniquePush(codes, code, code.toLocaleUpperCase("tr-TR")));

  return codes.values;
}

export function hasProductTargetSelection(selection) {
  return (selection?.targetRefs?.length ?? 0) > 0 || (selection?.targetCodes?.length ?? 0) > 0;
}
