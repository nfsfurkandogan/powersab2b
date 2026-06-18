"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  BadgeCheck,
  Clock3,
  Download,
  FileImage,
  FileText,
  Loader2,
  Paperclip,
  Search,
  ShieldCheck,
  Users,
  UserPlus,
  XCircle,
} from "lucide-react";
import { toast } from "sonner";

import { useSession } from "@/components/auth/session-provider";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import {
  convertCustomerCardRequestToCustomer,
  createCustomerCardRequest,
  listCustomerCardSalespeople,
  listCustomerCardRequests,
  resolveApiBaseUrl,
  type CustomerCardRequestListItem,
  uploadCustomerCardRequestAttachment,
  updateCustomerCardRequestStatus,
} from "@/lib/api";

type StatusFilter = "all" | "submitted" | "reviewing" | "approved" | "rejected";
type WorkflowStatus = Exclude<StatusFilter, "all">;
type AttachmentType = "photo" | "tax_plate" | "tax_certificate" | "trade_registry" | "other";
type CustomerKind = "person" | "company";
type FormState = {
  company_name: string;
  contact_name: string;
  phone: string;
  email: string;
  salesperson_user_id: string;
  customer_kind: CustomerKind;
  logo_authorization_code: string;
  city: string;
  district: string;
  tax_office: string;
  tax_number: string;
  address: string;
  note: string;
};

const STATUS_OPTIONS: Array<{ value: StatusFilter; label: string }> = [
  { value: "all", label: "Tüm Başvurular" },
  { value: "submitted", label: "Yeni" },
  { value: "reviewing", label: "İncelemede" },
  { value: "approved", label: "Onaylandı" },
  { value: "rejected", label: "Reddedildi" },
];

const REVIEW_ACTIONS: Record<WorkflowStatus, Array<{ status: WorkflowStatus; label: string; variant: "default" | "secondary" | "outline" | "destructive" }>> = {
  submitted: [
    { status: "reviewing", label: "İncelemeye Al", variant: "outline" },
    { status: "approved", label: "Onayla", variant: "secondary" },
    { status: "rejected", label: "Reddet", variant: "destructive" },
  ],
  reviewing: [
    { status: "approved", label: "Onayla", variant: "secondary" },
    { status: "rejected", label: "Reddet", variant: "destructive" },
  ],
  approved: [],
  rejected: [],
};

const ATTACHMENT_TYPE_OPTIONS: Array<{ value: AttachmentType; label: string }> = [
  { value: "photo", label: "Fotoğraf" },
  { value: "tax_plate", label: "Vergi Levhası" },
  { value: "tax_certificate", label: "Vergi Belgesi" },
  { value: "trade_registry", label: "Ticaret Sicil" },
  { value: "other", label: "Diğer Evrak" },
];

const SHELL_CARD_CLASSNAME =
  "overflow-hidden bg-[linear-gradient(180deg,var(--surface)_0%,var(--surface-soft)_100%)] shadow-[0_18px_34px_-28px_rgba(33,52,22,0.28)]";
const INSET_CARD_CLASSNAME =
  "rounded-2xl bg-[var(--surface)] shadow-[0_12px_24px_-22px_rgba(0,0,0,0.14)]";
const SOFT_PANEL_CLASSNAME =
  "rounded-2xl bg-[linear-gradient(180deg,var(--surface)_0%,var(--surface-soft)_100%)] shadow-[0_12px_24px_-22px_rgba(0,0,0,0.14)]";
const FIELD_CLASSNAME =
  "border-[var(--brand-border)] bg-[var(--surface)] text-[var(--foreground)] placeholder:text-[var(--muted-foreground)]";

function createInitialForm(user?: { name?: string | null; email?: string | null; phone?: string | null } | null): FormState {
  return {
    company_name: "",
    contact_name: user?.name ?? "",
    phone: user?.phone ?? "",
    email: user?.email ?? "",
    salesperson_user_id: "",
    customer_kind: "company",
    logo_authorization_code: "",
    city: "",
    district: "",
    tax_office: "",
    tax_number: "",
    address: "",
    note: "",
  };
}

const CUSTOMER_KIND_OPTIONS: Array<{ value: CustomerKind; label: string }> = [
  { value: "company", label: "Tüzel" },
  { value: "person", label: "Şahıs" },
];

const LOGO_AUTHORIZATION_CODE_OPTIONS = ["A", "D", "K"];

function formatDate(value: string | null): string {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("tr-TR", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(parsed);
}

function formatCount(value: number | undefined): string {
  return new Intl.NumberFormat("tr-TR", { maximumFractionDigits: 0 }).format(value ?? 0);
}

function formatFileSize(value: number | undefined): string {
  const size = value ?? 0;

  if (size >= 1024 * 1024) {
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }

  if (size >= 1024) {
    return `${Math.round(size / 1024)} KB`;
  }

  return `${size} B`;
}

function attachmentTypeLabel(type: string): string {
  return ATTACHMENT_TYPE_OPTIONS.find((item) => item.value === type)?.label ?? type;
}

function getStatusMeta(status: string) {
  const normalized = status.toLowerCase();

  if (normalized === "submitted") {
    return {
      label: "Yeni Başvuru",
      className: "border-amber-200 bg-amber-50 text-amber-700",
      icon: Clock3,
    };
  }

  if (normalized === "reviewing") {
    return {
      label: "İncelemede",
      className: "border-blue-200 bg-blue-50 text-blue-700",
      icon: ShieldCheck,
    };
  }

  if (normalized === "approved") {
    return {
      label: "Onaylandı",
      className: "border-emerald-200 bg-emerald-50 text-emerald-700",
      icon: BadgeCheck,
    };
  }

  return {
    label: "Reddedildi",
    className: "border-rose-200 bg-rose-50 text-rose-700",
    icon: XCircle,
  };
}

function getDefaultReviewNote(status: WorkflowStatus): string {
  if (status === "reviewing") {
    return "Başvuru inceleme kuyruğuna alındı.";
  }

  if (status === "approved") {
    return "Cari kart açılışı için başvuru onaylandı.";
  }

  return "Başvuru mevcut bilgilerle reddedildi.";
}

function logoECollectionPreview(): string {
  return `e ( yeni cari - ${new Intl.DateTimeFormat("tr-TR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(new Date())} )`;
}

function RequestCard({
  item,
  note,
  busy,
  converting,
  uploading,
  canReview,
  canCreateCustomer,
  onReviewNoteChange,
  onStatusClick,
  onCreateCustomer,
  onUploadAttachment,
}: {
  item: CustomerCardRequestListItem;
  note: string;
  busy: boolean;
  converting: boolean;
  uploading: boolean;
  canReview: boolean;
  canCreateCustomer: boolean;
  onReviewNoteChange: (value: string) => void;
  onStatusClick: (status: WorkflowStatus) => void;
  onCreateCustomer: () => void;
  onUploadAttachment: (payload: { file: File; attachment_type: AttachmentType; note?: string }) => Promise<void>;
}) {
  const statusMeta = getStatusMeta(item.status);
  const actions = REVIEW_ACTIONS[(item.status as WorkflowStatus) ?? "submitted"] ?? [];
  const StatusIcon = statusMeta.icon;
  const requirementState = item.attachment_requirements;
  const uploadedTypes = useMemo(() => new Set(requirementState.uploaded_types), [requirementState.uploaded_types]);
  const missingRequirementLabels = requirementState.missing_types.map((entry) => entry.label);
  const approvalBlocked = !requirementState.is_complete;
  const [attachmentType, setAttachmentType] = useState<AttachmentType>("photo");
  const [attachmentNote, setAttachmentNote] = useState("");
  const [attachmentFile, setAttachmentFile] = useState<File | null>(null);
  const attachmentBaseUrl = resolveApiBaseUrl();

  async function handleUploadClick() {
    if (!attachmentFile) {
      toast.error("Önce bir dosya seçin.");
      return;
    }

    await onUploadAttachment({
      file: attachmentFile,
      attachment_type: attachmentType,
      note: attachmentNote,
    });

    setAttachmentFile(null);
    setAttachmentNote("");
  }

  return (
    <Card className={SHELL_CARD_CLASSNAME}>
      <CardContent className="space-y-4 p-5">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h3 className="text-lg font-extrabold tracking-tight text-[var(--brand-primary-strong)]">{item.company_name}</h3>
              <Badge className={statusMeta.className}>
                <StatusIcon className="mr-1 h-3.5 w-3.5" />
                {statusMeta.label}
              </Badge>
            </div>
            <p className="mt-1 text-sm font-semibold text-[var(--muted-foreground)]">{item.request_no}</p>
          </div>

          <div className={INSET_CARD_CLASSNAME + " px-3 py-2 text-xs font-semibold text-[var(--muted-foreground)]"}>
            Açan: {item.requested_by.name ?? "-"}
            <br />
            Oluşturma: {formatDate(item.created_at)}
          </div>
        </div>

        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div className={SOFT_PANEL_CLASSNAME + " p-3"}>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Yetkili</p>
            <p className="mt-2 font-bold text-[var(--brand-primary-strong)]">{item.contact_name}</p>
            <p className="mt-1 text-sm text-[var(--muted-foreground)]">{item.phone}</p>
          </div>
          <div className={SOFT_PANEL_CLASSNAME + " p-3"}>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Konum</p>
            <p className="mt-2 font-bold text-[var(--brand-primary-strong)]">{item.city}</p>
            <p className="mt-1 text-sm text-[var(--muted-foreground)]">{item.district || "İlçe yok"}</p>
          </div>
          <div className={SOFT_PANEL_CLASSNAME + " p-3"}>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Vergi</p>
            <p className="mt-2 font-bold text-[var(--brand-primary-strong)]">{item.tax_office || "Vergi dairesi yok"}</p>
            <p className="mt-1 text-sm text-[var(--muted-foreground)]">{item.tax_number || "Vergi no yok"}</p>
          </div>
          <div className={SOFT_PANEL_CLASSNAME + " p-3"}>
            <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Bayi</p>
            <p className="mt-2 font-bold text-[var(--brand-primary-strong)]">{item.dealer.name || "-"}</p>
            <p className="mt-1 text-sm text-[var(--muted-foreground)]">{item.dealer.code || "Kod yok"}</p>
          </div>
        </div>

        <div className="grid gap-3 xl:grid-cols-[1.1fr_0.9fr]">
          <div className={SOFT_PANEL_CLASSNAME + " space-y-3 p-4"}>
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Adres</p>
              <p className="mt-2 text-sm text-[var(--foreground)]">{item.address || "Adres bilgisi girilmedi."}</p>
            </div>
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Başvuru Notu</p>
              <p className="mt-2 text-sm text-[var(--foreground)]">{item.note || "Açıklama girilmedi."}</p>
            </div>
            {item.email ? (
              <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">E-posta</p>
                <p className="mt-2 text-sm text-[var(--foreground)]">{item.email}</p>
              </div>
            ) : null}

            <div className="rounded-2xl bg-[var(--surface)] p-4 shadow-[0_12px_24px_-24px_rgba(0,0,0,0.14)]">
              <p className="flex items-center gap-2 text-sm font-bold text-[var(--brand-primary-strong)]">
                <Paperclip className="h-4 w-4" />
                Evrak / Fotoğraf Yükle
              </p>
              <div className="mt-3 grid gap-3 md:grid-cols-2">
                <Select value={attachmentType} onValueChange={(value) => setAttachmentType(value as AttachmentType)}>
                  <SelectTrigger className={FIELD_CLASSNAME}>
                    <SelectValue placeholder="Dosya tipi seç" />
                  </SelectTrigger>
                  <SelectContent>
                    {ATTACHMENT_TYPE_OPTIONS.map((option) => (
                      <SelectItem key={option.value} value={option.value}>
                        {option.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Input
                  type="file"
                  accept=".jpg,.jpeg,.png,.webp,.pdf"
                  onChange={(event) => setAttachmentFile(event.target.files?.[0] ?? null)}
                  className={FIELD_CLASSNAME + " cursor-pointer"}
                />
              </div>
              <Textarea
                value={attachmentNote}
                onChange={(event) => setAttachmentNote(event.target.value)}
                placeholder="Dosya açıklaması veya notu"
                className={FIELD_CLASSNAME + " mt-3 min-h-[84px]"}
              />
              <div className="mt-3 flex items-center gap-3">
                <Button
                  size="sm"
                  className="bg-[linear-gradient(135deg,var(--brand-primary)_0%,#d8df72_100%)] text-[var(--primary-foreground)] hover:opacity-95"
                  disabled={uploading}
                  onClick={() => void handleUploadClick()}
                >
                  {uploading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Paperclip className="h-4 w-4" />}
                  Dosya Yükle
                </Button>
                <p className="text-xs text-[var(--muted-foreground)]">
                  Desteklenen formatlar: JPG, PNG, WEBP, PDF. Maksimum 10 MB.
                </p>
              </div>
            </div>

            <div className={INSET_CARD_CLASSNAME + " p-4"}>
              <p className="flex items-center gap-2 text-sm font-bold text-[var(--brand-primary-strong)]">
                <ShieldCheck className="h-4 w-4" />
                Onay İçin Zorunlu Evraklar
              </p>
              <div className="mt-3 grid gap-2 md:grid-cols-3">
                {requirementState.required_types.map((requirement) => {
                  const uploaded = uploadedTypes.has(requirement.type);

                  return (
                    <div
                      key={requirement.type}
                      className={`rounded-xl px-3 py-2 ${
                        uploaded
                          ? "bg-emerald-50 text-emerald-800"
                          : "bg-amber-50 text-amber-800"
                      }`}
                    >
                      <p className="flex items-center gap-2 text-sm font-bold">
                        {uploaded ? <BadgeCheck className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                        {requirement.label}
                      </p>
                      <p className="mt-1 text-xs font-medium">{uploaded ? "Yüklendi" : "Eksik"}</p>
                    </div>
                  );
                })}
              </div>
              <p
                className={`mt-3 text-sm font-semibold ${
                  approvalBlocked ? "text-amber-700" : "text-emerald-700"
                }`}
              >
                {approvalBlocked
                  ? `Onay için eksik evraklar: ${missingRequirementLabels.join(", ")}`
                  : "Onay için zorunlu evraklar tamamlandı."}
              </p>
            </div>
          </div>

          <div className={SOFT_PANEL_CLASSNAME + " space-y-3 p-4"}>
            <div>
              <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-[var(--muted-foreground)]">Operasyon Notu</p>
              <Textarea
                value={note}
                onChange={(event) => onReviewNoteChange(event.target.value)}
                placeholder="İnceleme veya sonuç notunu yazın"
                className={FIELD_CLASSNAME + " mt-2 min-h-[110px]"}
              />
            </div>

            {item.review_note ? (
              <div className="rounded-xl bg-[var(--surface)] p-3 text-sm text-[var(--muted-foreground)] shadow-[0_10px_18px_-20px_rgba(0,0,0,0.12)]">
                Son not: {item.review_note}
                {item.reviewed_by.name ? ` • ${item.reviewed_by.name}` : ""}
                {item.reviewed_at ? ` • ${formatDate(item.reviewed_at)}` : ""}
              </div>
            ) : null}

            {item.customer.id ? (
              <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                <p className="font-bold">Cari açıldı: {item.customer.code}</p>
                <p className="mt-1">{item.customer.title}</p>
                <p className="mt-1">
                  {item.converted_by.name ? `${item.converted_by.name} • ` : ""}
                  {item.converted_at ? formatDate(item.converted_at) : "Tarih yok"}
                </p>
              </div>
            ) : null}

            {canReview && actions.length > 0 ? (
              <div className="space-y-2">
                <div className="flex flex-wrap gap-2">
                  {actions.map((action) => (
                    <Button
                      key={action.status}
                      variant={action.variant}
                      size="sm"
                      disabled={busy || (action.status === "approved" && approvalBlocked)}
                      onClick={() => onStatusClick(action.status)}
                    >
                      {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                      {action.label}
                    </Button>
                  ))}
                </div>
                {approvalBlocked ? (
                  <div className="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Onay aksiyonu açılmadan önce zorunlu evrak seti tamamlanmalı.
                  </div>
                ) : null}
              </div>
            ) : item.status === "approved" && !canCreateCustomer && !item.customer.id && approvalBlocked ? (
              <div className="rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Başvuru onaylı görünse de zorunlu evrak seti tamamlanmadan cari açılışı ilerletilmez.
              </div>
            ) : !canCreateCustomer && !item.customer.id ? (
              <p className="text-sm font-semibold text-[var(--muted-foreground)]">Bu başvuru için açık aksiyon kalmadı.</p>
            ) : null}

            {canCreateCustomer ? (
              <Button
                className="bg-[linear-gradient(135deg,var(--brand-primary)_0%,#d8df72_100%)] text-[var(--primary-foreground)] hover:opacity-95"
                disabled={converting}
                onClick={onCreateCustomer}
              >
                {converting ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus className="h-4 w-4" />}
                Cari Aç
              </Button>
            ) : null}
          </div>
        </div>

        <div className={SOFT_PANEL_CLASSNAME + " p-4"}>
          <p className="flex items-center gap-2 text-sm font-bold text-[var(--brand-primary-strong)]">
            <Paperclip className="h-4 w-4" />
            Yüklenen Evraklar
          </p>
          {item.attachments.length === 0 ? (
            <p className="mt-3 text-sm text-[var(--muted-foreground)]">Henüz dosya yüklenmedi.</p>
          ) : (
            <div className="mt-3 grid gap-3 md:grid-cols-2">
              {item.attachments.map((attachment) => {
                const isImage = attachment.mime_type?.startsWith("image/") ?? false;
                const FileIcon = isImage ? FileImage : FileText;

                return (
                  <div key={attachment.id} className={INSET_CARD_CLASSNAME + " p-4"}>
                    <div className="flex items-start justify-between gap-3">
                      <div className="min-w-0">
                        <p className="flex items-center gap-2 text-sm font-bold text-[var(--brand-primary-strong)]">
                          <FileIcon className="h-4 w-4 shrink-0" />
                          <span className="truncate">{attachment.original_name}</span>
                        </p>
                        <p className="mt-1 text-xs font-semibold text-[var(--muted-foreground)]">
                          {attachmentTypeLabel(attachment.attachment_type)} • {formatFileSize(attachment.size_bytes)}
                        </p>
                        <p className="mt-2 text-xs text-[var(--muted-foreground)]">
                          {attachment.note || "Not yok"}
                        </p>
                        <p className="mt-2 text-xs text-[var(--muted-foreground)]">
                          {attachment.uploaded_by.name ?? "-"} • {formatDate(attachment.uploaded_at)}
                        </p>
                      </div>

                      <Button asChild variant="outline" size="sm" className="border-[var(--brand-border)] bg-[var(--surface-soft)]">
                        <a href={`${attachmentBaseUrl}${attachment.download_url}`} target="_blank" rel="noreferrer">
                          <Download className="h-4 w-4" />
                          Aç
                        </a>
                      </Button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

export function NewCustomerCardPage() {
  const queryClient = useQueryClient();
  const { user } = useSession();
  const roleSlugs = user?.roles.map((role) => role.slug) ?? [];
  const canReviewCustomerCardRequests = roleSlugs.includes("admin") || roleSlugs.includes("salesperson");
  const isSalesperson = roleSlugs.includes("salesperson");
  const [form, setForm] = useState<FormState>(() => createInitialForm(user));
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<StatusFilter>("all");
  const [reviewNotes, setReviewNotes] = useState<Record<number, string>>({});
  const [pendingStatusId, setPendingStatusId] = useState<number | null>(null);
  const [pendingConvertId, setPendingConvertId] = useState<number | null>(null);
  const [pendingAttachmentId, setPendingAttachmentId] = useState<number | null>(null);
  const debouncedSearch = useDebouncedValue(search, 300);

  const salespeopleQuery = useQuery({
    queryKey: ["customer-card-salespeople"],
    queryFn: () => listCustomerCardSalespeople(),
    enabled: Boolean(user),
    staleTime: 5 * 60_000,
  });

  const requestsQuery = useQuery({
    queryKey: ["customer-card-requests", debouncedSearch, statusFilter],
    queryFn: () =>
      listCustomerCardRequests({
        q: debouncedSearch || undefined,
        statuses: statusFilter === "all" ? undefined : [statusFilter],
        limit: 24,
      }),
    enabled: Boolean(user),
    staleTime: 60_000,
  });

  const createMutation = useMutation({
    mutationFn: createCustomerCardRequest,
    onSuccess: (response) => {
      toast.success(response.customer?.code ? `Cari oluşturuldu: ${response.customer.code}` : "Yeni cari kart kaydedildi.");
      setForm(createInitialForm(user));
      void queryClient.invalidateQueries({ queryKey: ["customer-card-requests"] });
      void queryClient.invalidateQueries({ queryKey: ["customers"] });
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Başvuru kaydedilemedi.");
    },
  });

  const updateStatusMutation = useMutation({
    mutationFn: ({ requestId, status, review_note }: { requestId: number; status: WorkflowStatus; review_note?: string }) =>
      updateCustomerCardRequestStatus(requestId, { status, review_note }),
    onMutate: ({ requestId }) => {
      setPendingStatusId(requestId);
    },
    onSuccess: (_, variables) => {
      toast.success("Başvuru durumu güncellendi.");
      setReviewNotes((current) => ({ ...current, [variables.requestId]: "" }));
      void queryClient.invalidateQueries({ queryKey: ["customer-card-requests"] });
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Başvuru durumu güncellenemedi.");
    },
    onSettled: () => {
      setPendingStatusId(null);
    },
  });

  const convertMutation = useMutation({
    mutationFn: (requestId: number) => convertCustomerCardRequestToCustomer(requestId),
    onMutate: (requestId) => {
      setPendingConvertId(requestId);
    },
    onSuccess: (response) => {
      toast.success(`Cari açıldı: ${response.customer.code}`);
      void queryClient.invalidateQueries({ queryKey: ["customer-card-requests"] });
      void queryClient.invalidateQueries({ queryKey: ["customers"] });
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Cari açılamadı.");
    },
    onSettled: () => {
      setPendingConvertId(null);
    },
  });

  const uploadAttachmentMutation = useMutation({
    mutationFn: ({
      requestId,
      attachment_type,
      note,
      file,
    }: {
      requestId: number;
      attachment_type: AttachmentType;
      note?: string;
      file: File;
    }) => uploadCustomerCardRequestAttachment(requestId, { attachment_type, note, file }),
    onMutate: ({ requestId }) => {
      setPendingAttachmentId(requestId);
    },
    onSuccess: () => {
      toast.success("Dosya yüklendi.");
      void queryClient.invalidateQueries({ queryKey: ["customer-card-requests"] });
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Dosya yüklenemedi.");
    },
    onSettled: () => {
      setPendingAttachmentId(null);
    },
  });

  const requestItems = requestsQuery.data?.data ?? [];
  const salespersonOptions = useMemo(() => salespeopleQuery.data?.data ?? [], [salespeopleQuery.data?.data]);
  const isCompanyCustomer = form.customer_kind === "company";
  const eCollectionPreview = useMemo(() => logoECollectionPreview(), []);
  const ownSalesperson = useMemo(
    () => salespersonOptions.find((salesperson) => salesperson.id === user?.id) ?? salespersonOptions[0] ?? null,
    [salespersonOptions, user?.id]
  );
  const selectedSalespersonId = isSalesperson
    ? String(ownSalesperson?.id ?? user?.id ?? "")
    : form.salesperson_user_id;

  function updateField<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function handleSubmit() {
    if (!form.company_name.trim() || !form.phone.trim() || !form.city.trim() || !form.address.trim()) {
      toast.error("Cari ismi, telefon, il ve adres alanları zorunlu.");
      return;
    }

    if (isCompanyCustomer && (!form.tax_office.trim() || !form.tax_number.trim())) {
      toast.error("Tüzel cari için vergi dairesi ve vergi no zorunlu.");
      return;
    }

    if (!selectedSalespersonId.trim()) {
      toast.error("Plasiyer seçimi zorunlu.");
      return;
    }

    createMutation.mutate({
      salesperson_user_id: Number(selectedSalespersonId),
      company_name: form.company_name.trim(),
      contact_name: form.contact_name.trim() || form.company_name.trim(),
      phone: form.phone.trim(),
      email: form.email.trim() || undefined,
      customer_kind: form.customer_kind,
      logo_authorization_code: form.logo_authorization_code.trim() || undefined,
      auto_convert: true,
      city: form.city.trim(),
      district: form.district.trim() || undefined,
      tax_office: form.tax_office.trim() || undefined,
      tax_number: form.tax_number.trim() || undefined,
      address: form.address.trim() || undefined,
      note: form.note.trim() || undefined,
    });
  }

  function handleStatusAction(item: CustomerCardRequestListItem, status: WorkflowStatus) {
    if (status === "approved" && !item.attachment_requirements.is_complete) {
      const missingLabels = item.attachment_requirements.missing_types.map((entry) => entry.label);
      toast.error(`Onay için zorunlu evraklar eksik: ${missingLabels.join(", ")}`);
      return;
    }

    const reviewNote = reviewNotes[item.id]?.trim() || getDefaultReviewNote(status);

    updateStatusMutation.mutate({
      requestId: item.id,
      status,
      review_note: reviewNote,
    });
  }

  function handleCreateCustomer(item: CustomerCardRequestListItem) {
    convertMutation.mutate(item.id);
  }

  async function handleUploadAttachment(
    item: CustomerCardRequestListItem,
    payload: { file: File; attachment_type: AttachmentType; note?: string }
  ) {
    await uploadAttachmentMutation.mutateAsync({
      requestId: item.id,
      ...payload,
    });
  }

  return (
    <div className="space-y-4">
      <Card className={SHELL_CARD_CLASSNAME}>
        <CardContent className="p-4 sm:p-6">
          <div className="flex items-center gap-3">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-[var(--brand-border)] bg-[var(--surface-soft)] text-[var(--brand-primary)]">
              <UserPlus className="h-6 w-6" />
            </div>
            <h2 className="text-2xl font-extrabold tracking-tight text-[var(--brand-primary-strong)]">Yeni Cari Oluştur</h2>
          </div>

          <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Cari İsmi</label>
              <Input
                className={FIELD_CLASSNAME}
                value={form.company_name}
                onChange={(event) => updateField("company_name", event.target.value)}
                placeholder="Cari adı"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Şahıs / Tüzel</label>
              <Select value={form.customer_kind} onValueChange={(value) => updateField("customer_kind", value as CustomerKind)}>
                <SelectTrigger className={FIELD_CLASSNAME}>
                  <SelectValue placeholder="Cari tipi seç" />
                </SelectTrigger>
                <SelectContent>
                  {CUSTOMER_KIND_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Telefon</label>
              <Input
                className={FIELD_CLASSNAME}
                value={form.phone}
                onChange={(event) => updateField("phone", event.target.value)}
                placeholder="05xx xxx xx xx"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Mail</label>
              <Input
                className={FIELD_CLASSNAME}
                value={form.email}
                onChange={(event) => updateField("email", event.target.value)}
                placeholder="mail zorunlu değil"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">İl</label>
              <Input
                className={FIELD_CLASSNAME}
                value={form.city}
                onChange={(event) => updateField("city", event.target.value)}
                placeholder="İl"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">İlçe</label>
              <Input
                className={FIELD_CLASSNAME}
                value={form.district}
                onChange={(event) => updateField("district", event.target.value)}
                placeholder="İlçe"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Plasiyer</label>
              <Select
                value={selectedSalespersonId}
                onValueChange={(value) => updateField("salesperson_user_id", value)}
                disabled={isSalesperson || salespeopleQuery.isLoading}
              >
                <SelectTrigger className={FIELD_CLASSNAME}>
                  <SelectValue placeholder={salespeopleQuery.isLoading ? "Plasiyerler yükleniyor" : "Plasiyer seç"} />
                </SelectTrigger>
                <SelectContent>
                  {salespersonOptions.map((salesperson) => (
                    <SelectItem key={salesperson.id} value={String(salesperson.id)}>
                      {salesperson.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Özel Kodu</label>
              <Input className={FIELD_CLASSNAME + " font-bold"} value="F1" readOnly />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Yetki Kodu</label>
              <Select value={form.logo_authorization_code || "none"} onValueChange={(value) => updateField("logo_authorization_code", value === "none" ? "" : value)}>
                <SelectTrigger className={FIELD_CLASSNAME}>
                  <SelectValue placeholder="Yetki kodu seç" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Boş</SelectItem>
                  {LOGO_AUTHORIZATION_CODE_OPTIONS.map((option) => (
                    <SelectItem key={option} value={option}>
                      {option}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {isCompanyCustomer ? (
              <>
                <div className="space-y-2">
                  <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Vergi Dairesi</label>
                  <Input
                    className={FIELD_CLASSNAME}
                    value={form.tax_office}
                    onChange={(event) => updateField("tax_office", event.target.value)}
                    placeholder="Vergi dairesi"
                  />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Vergi No</label>
                  <Input
                    className={FIELD_CLASSNAME}
                    value={form.tax_number}
                    onChange={(event) => updateField("tax_number", event.target.value)}
                    placeholder="10 haneli vergi no"
                  />
                </div>
              </>
            ) : (
              <div className="space-y-2">
                <label className="text-sm font-bold text-[var(--brand-primary-strong)]">T.C. Kimlik No</label>
                <Input
                  className={FIELD_CLASSNAME}
                  value={form.tax_number}
                  onChange={(event) => updateField("tax_number", event.target.value)}
                  placeholder="11 haneli T.C. kimlik no"
                />
              </div>
            )}
            <div className="space-y-2 md:col-span-2 xl:col-span-3">
              <label className="text-sm font-bold text-[var(--brand-primary-strong)]">Adres</label>
              <Textarea
                className={FIELD_CLASSNAME + " min-h-[96px]"}
                value={form.address}
                onChange={(event) => updateField("address", event.target.value)}
                placeholder="Açık adres"
              />
            </div>
            <div className={SOFT_PANEL_CLASSNAME + " grid gap-3 p-4 md:col-span-2 md:grid-cols-2 xl:col-span-3"}>
              <p className="flex items-center gap-2 text-sm font-bold text-[var(--brand-primary-strong)]">
                <Users className="h-4 w-4" />
                Logo E-Tahsilat
              </p>
              <div className="rounded-xl bg-[var(--surface)] px-3 py-2 text-sm font-black text-[var(--brand-primary-strong)] shadow-[0_10px_18px_-20px_rgba(0,0,0,0.12)]">
                {eCollectionPreview}
              </div>
            </div>
          </div>

          <div className="mt-5 flex flex-wrap justify-end gap-2">
            <Button
              variant="outline"
              className="border-[var(--brand-border)] bg-[var(--surface)]"
              onClick={() => setForm(createInitialForm(user))}
              disabled={createMutation.isPending}
            >
              Temizle
            </Button>
            <Button
              className="bg-[linear-gradient(135deg,var(--brand-primary)_0%,#d8df72_100%)] px-6 text-[var(--primary-foreground)] hover:opacity-95"
              onClick={handleSubmit}
              disabled={createMutation.isPending}
            >
              {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserPlus className="h-4 w-4" />}
              Cari Oluştur
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className={SHELL_CARD_CLASSNAME}>
        <CardContent className="space-y-4 p-4 sm:p-5">
          <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="flex items-center gap-2">
              <FileText className="h-5 w-5 text-[var(--brand-primary)]" />
              <h2 className="text-lg font-extrabold text-[var(--brand-primary-strong)]">Son Başvurular</h2>
              <Badge className="border-[var(--brand-border)] bg-[var(--surface-soft)] text-[var(--brand-primary-strong)]">
                {formatCount(requestItems.length)} kayıt
              </Badge>
            </div>

            <div className="grid gap-2 md:grid-cols-[minmax(0,300px)_180px]">
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted-foreground)]" />
                <Input
                  className={FIELD_CLASSNAME + " pl-9"}
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Firma veya telefon ara"
                />
              </div>
              <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as StatusFilter)}>
                <SelectTrigger className={FIELD_CLASSNAME}>
                  <SelectValue placeholder="Durum filtrele" />
                </SelectTrigger>
                <SelectContent>
                  {STATUS_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          {requestsQuery.isLoading ? (
            Array.from({ length: 3 }).map((_, index) => <Skeleton key={`customer-card-request-${index}`} className="h-56 w-full" />)
          ) : requestItems.length === 0 ? (
            <div className="rounded-3xl bg-[var(--surface)] px-6 py-12 text-center shadow-[0_14px_28px_-26px_rgba(0,0,0,0.12)]">
              <p className="text-lg font-extrabold text-[var(--brand-primary-strong)]">Henüz kayıt yok</p>
            </div>
          ) : (
            requestItems.map((item) => (
              <RequestCard
                key={item.id}
                item={item}
                note={reviewNotes[item.id] ?? ""}
                busy={pendingStatusId === item.id}
                converting={pendingConvertId === item.id}
                uploading={pendingAttachmentId === item.id}
                canReview={canReviewCustomerCardRequests}
                canCreateCustomer={
                  canReviewCustomerCardRequests &&
                  item.status === "approved" &&
                  item.customer.id === null &&
                  item.attachment_requirements.is_complete
                }
                onReviewNoteChange={(value) => setReviewNotes((current) => ({ ...current, [item.id]: value }))}
                onStatusClick={(status) => handleStatusAction(item, status)}
                onCreateCustomer={() => handleCreateCustomer(item)}
                onUploadAttachment={(payload) => handleUploadAttachment(item, payload)}
              />
            ))
          )}
        </CardContent>
      </Card>
    </div>
  );
}
