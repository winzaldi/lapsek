import { NextRequest, NextResponse } from "next/server";

const cookieName = "laos_session";
const maxBodyBytes = 1_000_000;

async function proxy(
  request: NextRequest,
  context: { params: Promise<{ path: string[] }> },
) {
  const origin = request.headers.get("origin");
  if (request.method !== "GET" && origin && origin !== request.nextUrl.origin) {
    return NextResponse.json({ message: "Permintaan lintas situs ditolak." }, { status: 403 });
  }

  const token = request.cookies.get(cookieName)?.value;
  if (!token) return NextResponse.json({ message: "Sesi tidak ditemukan." }, { status: 401 });

  const baseUrl = process.env.LAOS_API_BASE_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL;
  if (!baseUrl) {
    return NextResponse.json({ message: "Alamat API belum dikonfigurasi." }, { status: 500 });
  }

  const { path } = await context.params;
  if (!path.length || path.some((part) => !part || part === "." || part === ".." || part.includes("/"))) {
    return NextResponse.json({ message: "Rute API tidak valid." }, { status: 400 });
  }

  let body: string | undefined;
  if (request.method !== "GET") {
    const contentLength = Number(request.headers.get("content-length") ?? 0);
    if (contentLength > maxBodyBytes) {
      return NextResponse.json({ message: "Ukuran permintaan melebihi batas." }, { status: 413 });
    }
    const contentType = request.headers.get("content-type") ?? "";
    if (!contentType.toLowerCase().includes("application/json")) {
      return NextResponse.json({ message: "Format permintaan harus JSON." }, { status: 415 });
    }
    body = await request.text();
    if (new TextEncoder().encode(body).byteLength > maxBodyBytes) {
      return NextResponse.json({ message: "Ukuran permintaan melebihi batas." }, { status: 413 });
    }
  }

  const url = `${baseUrl.replace(/\/+$/, "")}/${path.map(encodeURIComponent).join("/")}${request.nextUrl.search}`;
  try {
    const response = await fetch(url, {
      method: request.method,
      cache: "no-store",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
        ...(body ? { "Content-Type": "application/json" } : {}),
      },
      body,
    });
    const forwardedHeaders = new Headers({
      "Content-Type": response.headers.get("content-type") ?? "application/json",
      "Cache-Control": response.headers.get("cache-control") ?? "no-store",
    });
    for (const header of ["content-disposition", "content-length", "x-content-type-options"]) {
      const value = response.headers.get(header);
      if (value) forwardedHeaders.set(header, value);
    }
    const result = new NextResponse(response.body, {
      status: response.status,
      headers: forwardedHeaders,
    });
    if (response.status === 401) result.cookies.delete(cookieName);
    return result;
  } catch (error) {
    console.error("Unable to reach the LAOS API.", error);
    return NextResponse.json({ message: "Layanan API tidak dapat dihubungi." }, { status: 502 });
  }
}

export const GET = proxy;
export const POST = proxy;
export const PATCH = proxy;
export const PUT = proxy;
