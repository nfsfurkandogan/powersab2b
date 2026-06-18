export function formatRoleLabel(slug: string): string {
  const map: Record<string, string> = {
    admin: "Admin",
    dealer_admin: "Bayi",
    moderator: "Moderatör",
    salesperson: "Plasiyer",
    point: "Point",
    cashier: "Point",
    warehouse: "Depo",
    customer: "Müşteri",
  };

  return map[slug] ?? slug;
}
