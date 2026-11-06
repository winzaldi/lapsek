export type ApiError = Error & { status?: number; errors?: Record<string, string[]> };

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set("Accept", "application/json");
  if (init?.body) headers.set("Content-Type", "application/json");
  const response = await fetch(`/api/laos/${path.replace(/^\/+/, "")}`, {
    ...init,
    cache: "no-store",
    credentials: "same-origin",
    headers,
  });

  const body: unknown = await response.json().catch(() => null);
  if (!response.ok) {
    const payload = body as {
      message?: string;
      errors?: Record<string, string[]>;
    } | null;
    const error = new Error(
      payload?.message ?? `Permintaan gagal (HTTP ${response.status}).`,
    ) as ApiError;
    error.status = response.status;
    error.errors = payload?.errors;
    throw error;
  }

  return body as T;
}

export function rowsFrom<T>(payload: unknown): T[] {
  if (!payload || typeof payload !== "object") return [];
  const first = (payload as { data?: unknown }).data;
  if (Array.isArray(first)) return first as T[];
  if (first && typeof first === "object") {
    const nested = (first as { data?: unknown }).data;
    if (Array.isArray(nested)) return nested as T[];
  }
  return [];
}

export function formatApiError(error: unknown): string {
  if (error instanceof Error) {
    const apiError = error as ApiError;
    const details = apiError.errors
      ? Object.values(apiError.errors).flat().join(" ")
      : "";
    return details ? `${apiError.message} ${details}` : apiError.message;
  }
  return "Terjadi kesalahan yang tidak diketahui.";
}
