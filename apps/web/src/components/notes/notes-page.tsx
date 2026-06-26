"use client";

import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  CalendarDays,
  CheckCircle2,
  Circle,
  Loader2,
  NotebookPen,
  Pin,
  Plus,
  Trash2,
} from "lucide-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  createUserNote,
  deleteUserNote,
  listUserNotes,
  updateUserNote,
  type UserNote,
  type UserNotePriority,
  type UserNoteStatus,
} from "@/lib/api";
import { cn } from "@/lib/utils";

type NoteFilter = "open" | "done" | "all";

const FILTERS: Array<{ key: NoteFilter; label: string }> = [
  { key: "open", label: "Açık" },
  { key: "done", label: "Tamamlanan" },
  { key: "all", label: "Hepsi" },
];

const PRIORITIES: Array<{ key: UserNotePriority; label: string }> = [
  { key: "normal", label: "Normal" },
  { key: "high", label: "Önemli" },
  { key: "low", label: "Düşük" },
];

const EMPTY_NOTES: UserNote[] = [];
const FALLBACK_NOTE_OWNER = "Powersa";

const priorityClassNames: Record<UserNotePriority, string> = {
  high: "border-red-300/35 bg-red-400/12 text-red-100",
  normal: "border-emerald-300/25 bg-emerald-300/10 text-emerald-100",
  low: "border-sky-300/25 bg-sky-300/10 text-sky-100",
};

function formatDate(value: string | null) {
  if (!value) {
    return null;
  }

  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("tr-TR", {
    day: "2-digit",
    month: "short",
  }).format(date);
}

function noteOwner(note: UserNote) {
  return note.created_by?.name || note.created_by?.username || FALLBACK_NOTE_OWNER;
}

function normalizeCount(value: unknown) {
  const count = Number(value);

  return Number.isFinite(count) ? count : 0;
}

function isUserNotePriority(value: unknown): value is UserNotePriority {
  return value === "low" || value === "normal" || value === "high";
}

function notePriority(note: UserNote): UserNotePriority {
  return isUserNotePriority(note.priority) ? note.priority : "normal";
}

function noteStatus(note: UserNote): UserNoteStatus {
  return note.status === "done" ? "done" : "open";
}

function isRenderableNote(note: unknown): note is UserNote {
  if (!note || typeof note !== "object") {
    return false;
  }

  const candidate = note as Partial<UserNote>;

  return typeof candidate.id === "number" && typeof candidate.title === "string";
}

export function NotesPage() {
  const queryClient = useQueryClient();
  const [filter, setFilter] = useState<NoteFilter>("open");
  const [title, setTitle] = useState("");
  const [body, setBody] = useState("");
  const [priority, setPriority] = useState<UserNotePriority>("normal");
  const [dueDate, setDueDate] = useState("");
  const [pinned, setPinned] = useState(false);

  const notesQuery = useQuery({
    queryKey: ["user-notes", filter],
    queryFn: () => listUserNotes({ status: filter }),
    staleTime: 20_000,
  });

  const invalidateNotes = async () => {
    await queryClient.invalidateQueries({ queryKey: ["user-notes"] });
  };

  const createMutation = useMutation({
    mutationFn: createUserNote,
    onSuccess: async () => {
      setTitle("");
      setBody("");
      setPriority("normal");
      setDueDate("");
      setPinned(false);
      await invalidateNotes();
      toast.success("Not eklendi.");
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Not eklenemedi.");
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ noteId, payload }: { noteId: number; payload: Parameters<typeof updateUserNote>[1] }) =>
      updateUserNote(noteId, payload),
    onSuccess: async () => {
      await invalidateNotes();
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Not güncellenemedi.");
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteUserNote,
    onSuccess: async () => {
      await invalidateNotes();
      toast.success("Not silindi.");
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Not silinemedi.");
    },
  });

  const notes = Array.isArray(notesQuery.data?.data) ? notesQuery.data.data.filter(isRenderableNote) : EMPTY_NOTES;
  const rawSummary = notesQuery.data?.summary;
  const summary = {
    open: normalizeCount(rawSummary?.open),
    done: normalizeCount(rawSummary?.done),
  };
  const canCreate = title.trim() !== "" && !createMutation.isPending;
  const pinnedCount = useMemo(() => notes.filter((note) => Boolean(note.is_pinned)).length, [notes]);

  const handleCreate = () => {
    if (!canCreate) {
      toast.error("Not başlığı girin.");
      return;
    }

    createMutation.mutate({
      title: title.trim(),
      body: body.trim() || null,
      priority,
      due_date: dueDate || null,
      is_pinned: pinned,
    });
  };

  return (
    <div className="mx-auto flex w-full max-w-[1420px] flex-col gap-5">
      <section className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="rounded-[28px] border border-white/10 bg-[linear-gradient(145deg,rgba(10,22,28,0.96),rgba(8,18,24,0.98))] p-5 shadow-[0_24px_70px_-48px_rgba(0,0,0,0.95)]">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <span className="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-emerald-300/20 bg-emerald-300/10 text-emerald-200">
                <NotebookPen className="h-6 w-6" />
              </span>
              <div>
                <h1 className="text-2xl font-black tracking-tight text-white">Not Defteri</h1>
                <p className="mt-1 text-sm font-semibold text-slate-400">Ortak işler, hatırlatmalar ve günlük takip.</p>
              </div>
            </div>
            <div className="flex rounded-2xl border border-white/10 bg-white/[0.04] p-1">
              {FILTERS.map((item) => (
                <button
                  key={item.key}
                  type="button"
                  className={cn(
                    "h-10 rounded-xl px-4 text-sm font-black transition",
                    filter === item.key
                      ? "bg-emerald-300 text-[#0d251c]"
                      : "text-slate-300 hover:bg-white/[0.06] hover:text-white"
                  )}
                  onClick={() => setFilter(item.key)}
                >
                  {item.label}
                </button>
              ))}
            </div>
          </div>

          <div className="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_150px]">
            <Input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              placeholder="Not veya görev başlığı"
              className="h-12 rounded-2xl border-white/10 bg-slate-950/45 text-base font-bold text-white placeholder:text-slate-500"
            />
            <Input
              type="date"
              value={dueDate}
              onChange={(event) => setDueDate(event.target.value)}
              className="h-12 rounded-2xl border-white/10 bg-slate-950/45 font-bold text-slate-100"
            />
            <Button
              type="button"
              className="h-12 rounded-2xl bg-emerald-300 font-black text-[#0d251c] hover:bg-emerald-200"
              disabled={!canCreate}
              onClick={handleCreate}
            >
              {createMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />}
              Ekle
            </Button>
          </div>

          <Textarea
            value={body}
            onChange={(event) => setBody(event.target.value)}
            placeholder="Kısa açıklama, yapılacaklar veya hatırlatma..."
            className="mt-3 min-h-[92px] rounded-2xl border-white/10 bg-slate-950/45 text-sm font-semibold text-slate-100 placeholder:text-slate-500"
          />

          <div className="mt-3 flex flex-wrap items-center gap-2">
            {PRIORITIES.map((item) => (
              <button
                key={item.key}
                type="button"
                className={cn(
                  "h-10 rounded-xl border px-4 text-sm font-black transition",
                  priority === item.key
                    ? priorityClassNames[item.key]
                    : "border-white/10 bg-white/[0.035] text-slate-400 hover:bg-white/[0.07] hover:text-white"
                )}
                onClick={() => setPriority(item.key)}
              >
                {item.label}
              </button>
            ))}
            <button
              type="button"
              className={cn(
                "inline-flex h-10 items-center gap-2 rounded-xl border px-4 text-sm font-black transition",
                pinned
                  ? "border-[#f8e58b]/35 bg-[#f8e58b]/12 text-[#fff2a8]"
                  : "border-white/10 bg-white/[0.035] text-slate-400 hover:bg-white/[0.07] hover:text-white"
              )}
              onClick={() => setPinned((current) => !current)}
            >
              <Pin className="h-4 w-4" />
              Sabitle
            </button>
          </div>
        </div>

        <aside className="grid gap-3 rounded-[28px] border border-white/10 bg-white/[0.045] p-5">
          <div className="rounded-2xl border border-emerald-300/16 bg-emerald-300/10 p-4">
            <p className="text-xs font-black uppercase tracking-[0.14em] text-emerald-100/75">Açık Görev</p>
            <strong className="mt-2 block text-4xl font-black text-emerald-100">{summary.open}</strong>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="rounded-2xl border border-sky-300/16 bg-sky-300/10 p-4">
              <p className="text-xs font-black uppercase tracking-[0.14em] text-sky-100/75">Biten</p>
              <strong className="mt-2 block text-2xl font-black text-sky-100">{summary.done}</strong>
            </div>
            <div className="rounded-2xl border border-[#f8e58b]/16 bg-[#f8e58b]/10 p-4">
              <p className="text-xs font-black uppercase tracking-[0.14em] text-[#fff2a8]/75">Sabit</p>
              <strong className="mt-2 block text-2xl font-black text-[#fff2a8]">{pinnedCount}</strong>
            </div>
          </div>
        </aside>
      </section>

      <section className="min-h-[360px] rounded-[28px] border border-white/10 bg-slate-950/40 p-4">
        {notesQuery.isLoading ? (
          <div className="flex min-h-[280px] items-center justify-center text-slate-400">
            <Loader2 className="mr-2 h-5 w-5 animate-spin" />
            Notlar yükleniyor...
          </div>
        ) : notesQuery.isError ? (
          <div className="flex min-h-[280px] flex-col items-center justify-center text-center">
            <NotebookPen className="h-10 w-10 text-red-200/80" />
            <p className="mt-3 text-lg font-black text-white">Notlar yüklenemedi</p>
            <p className="mt-1 max-w-md text-sm font-semibold text-slate-500">
              {notesQuery.error instanceof Error ? notesQuery.error.message : "Lütfen tekrar deneyin."}
            </p>
            <Button
              type="button"
              variant="outline"
              className="mt-4 rounded-xl border-white/10 bg-white/[0.04] font-bold text-slate-100 hover:bg-white/[0.08]"
              onClick={() => notesQuery.refetch()}
            >
              Tekrar Dene
            </Button>
          </div>
        ) : notes.length === 0 ? (
          <div className="flex min-h-[280px] flex-col items-center justify-center text-center">
            <NotebookPen className="h-10 w-10 text-slate-500" />
            <p className="mt-3 text-lg font-black text-white">Henüz not yok</p>
            <p className="mt-1 max-w-md text-sm font-semibold text-slate-500">Yeni bir başlık yazıp ekleyin.</p>
          </div>
        ) : (
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {notes.map((note) => {
              const due = formatDate(note.due_date);
              const done = noteStatus(note) === "done";
              const priorityKey = notePriority(note);

              return (
                <article
                  key={note.id}
                  className={cn(
                    "group flex min-h-[210px] flex-col rounded-2xl border p-4 transition",
                    done
                      ? "border-white/8 bg-white/[0.035] opacity-75"
                      : "border-white/10 bg-[linear-gradient(145deg,rgba(16,31,38,0.94),rgba(11,23,31,0.98))] hover:border-emerald-300/24"
                  )}
                >
                  <div className="flex items-start justify-between gap-3">
                    <button
                      type="button"
                      className={cn("mt-0.5 text-slate-400 transition hover:text-emerald-200", done && "text-emerald-300")}
                      onClick={() =>
                        updateMutation.mutate({
                          noteId: note.id,
                          payload: { status: done ? "open" : "done" },
                        })
                      }
                      title={done ? "Açık yap" : "Tamamlandı"}
                    >
                      {done ? <CheckCircle2 className="h-6 w-6" /> : <Circle className="h-6 w-6" />}
                    </button>
                    <div className="min-w-0 flex-1">
                      <h2 className={cn("line-clamp-2 text-lg font-black leading-6 text-white", done && "line-through decoration-emerald-300/60")}>
                        {note.title}
                      </h2>
                      <p className="mt-1 text-xs font-bold text-slate-500">{noteOwner(note)}</p>
                    </div>
                    <button
                      type="button"
                      className={cn("rounded-lg p-1.5 text-slate-500 transition hover:bg-white/[0.06] hover:text-[#fff2a8]", note.is_pinned && "text-[#fff2a8]")}
                      onClick={() =>
                        updateMutation.mutate({
                          noteId: note.id,
                          payload: { is_pinned: !note.is_pinned },
                        })
                      }
                      title={note.is_pinned ? "Sabitlemeyi kaldır" : "Sabitle"}
                    >
                      <Pin className="h-4 w-4" />
                    </button>
                  </div>

                  {note.body ? (
                    <p className="mt-3 line-clamp-4 whitespace-pre-line text-sm font-semibold leading-6 text-slate-300">
                      {note.body}
                    </p>
                  ) : null}

                  <div className="mt-auto flex flex-wrap items-center gap-2 pt-4">
                    <span className={cn("rounded-full border px-3 py-1 text-xs font-black", priorityClassNames[priorityKey])}>
                      {PRIORITIES.find((item) => item.key === priorityKey)?.label ?? priorityKey}
                    </span>
                    {due ? (
                      <span className="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.045] px-3 py-1 text-xs font-black text-slate-300">
                        <CalendarDays className="h-3.5 w-3.5" />
                        {due}
                      </span>
                    ) : null}
                    <button
                      type="button"
                      className="ml-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-red-400/12 hover:text-red-100"
                      disabled={deleteMutation.isPending}
                      onClick={() => deleteMutation.mutate(note.id)}
                      title="Sil"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
}
