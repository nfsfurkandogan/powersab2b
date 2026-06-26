<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'done', 'all'])],
        ]);

        $query = UserNote::query()
            ->with('user:id,name,username');
        $this->applyVisibleNoteScope($query, $request->user());

        $status = $validated['status'] ?? 'open';
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $notes = $query
            ->orderByDesc('is_pinned')
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();
        $openSummaryQuery = UserNote::query();
        $this->applyVisibleNoteScope($openSummaryQuery, $request->user());
        $doneSummaryQuery = UserNote::query();
        $this->applyVisibleNoteScope($doneSummaryQuery, $request->user());

        return response()->json([
            'data' => $notes->map(fn (UserNote $note): array => $this->serializeNote($note))->values(),
            'summary' => [
                'open' => $openSummaryQuery
                    ->where('status', 'open')
                    ->count(),
                'done' => $doneSummaryQuery
                    ->where('status', 'done')
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $status = $validated['status'] ?? 'open';

        $note = UserNote::query()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'status' => $status,
            'priority' => $validated['priority'] ?? 'normal',
            'is_pinned' => (bool) ($validated['is_pinned'] ?? false),
            'due_date' => $validated['due_date'] ?? null,
            'completed_at' => $status === 'done' ? now() : null,
        ]);
        $note->load('user:id,name,username');

        return response()->json([
            'data' => $this->serializeNote($note),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, UserNote $note): JsonResponse
    {
        $this->authorizeVisibleNote($note, $request->user());

        $validated = $this->validatedPayload($request, partial: true);
        $nextStatus = $validated['status'] ?? $note->status;

        $note->fill([
            'title' => $validated['title'] ?? $note->title,
            'body' => array_key_exists('body', $validated) ? ($validated['body'] ?? null) : $note->body,
            'status' => $nextStatus,
            'priority' => $validated['priority'] ?? $note->priority,
            'is_pinned' => array_key_exists('is_pinned', $validated) ? (bool) $validated['is_pinned'] : $note->is_pinned,
            'due_date' => array_key_exists('due_date', $validated) ? ($validated['due_date'] ?? null) : $note->due_date,
            'completed_at' => $nextStatus === 'done'
                ? ($note->completed_at ?? now())
                : null,
        ])->save();

        return response()->json([
            'data' => $this->serializeNote($note->fresh(['user:id,name,username'])),
        ]);
    }

    public function destroy(Request $request, UserNote $note): JsonResponse
    {
        $this->authorizeVisibleNote($note, $request->user());

        $note->delete();

        return response()->json([
            'message' => 'Not silindi.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', Rule::in(['open', 'done'])],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high'])],
            'is_pinned' => ['sometimes', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNote(?UserNote $note): array
    {
        if (! $note instanceof UserNote) {
            return [];
        }

        return [
            'id' => (int) $note->id,
            'user_id' => (int) $note->user_id,
            'created_by' => [
                'id' => (int) $note->user_id,
                'name' => $note->user?->name,
                'username' => $note->user?->username,
            ],
            'title' => $note->title,
            'body' => $note->body,
            'status' => $note->status,
            'priority' => $note->priority,
            'is_pinned' => (bool) $note->is_pinned,
            'due_date' => $note->due_date?->format('Y-m-d'),
            'completed_at' => $note->completed_at?->toIso8601String(),
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    private function applyVisibleNoteScope(Builder $query, User $user): void
    {
        if ($this->usesPrivateNotes($user)) {
            $query->where('user_id', $user->id);

            return;
        }

        $query->whereDoesntHave('user', function ($userQuery): void {
            $userQuery->whereIn('username', $this->privateNoteUsernames());
        });
    }

    private function authorizeVisibleNote(UserNote $note, User $user): void
    {
        if ($this->usesPrivateNotes($user)) {
            abort_unless((int) $note->user_id === (int) $user->id, Response::HTTP_NOT_FOUND);

            return;
        }

        $noteUsername = mb_strtolower(trim((string) $note->user?->username), 'UTF-8');
        abort_if(in_array($noteUsername, $this->privateNoteUsernames(), true), Response::HTTP_NOT_FOUND);
    }

    private function usesPrivateNotes(User $user): bool
    {
        return in_array(
            mb_strtolower(trim((string) $user->username), 'UTF-8'),
            $this->privateNoteUsernames(),
            true
        );
    }

    /**
     * @return list<string>
     */
    private function privateNoteUsernames(): array
    {
        return ['erzurum.hizlisatis'];
    }
}
