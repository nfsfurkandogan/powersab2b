import { WarehouseShipmentDetailPage } from "@/components/warehouse/warehouse-shipment-detail-page";

export default async function WarehouseShipmentDetailRoutePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  return <WarehouseShipmentDetailPage shipmentId={id} />;
}
