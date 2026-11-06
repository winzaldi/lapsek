import { NextRequest, NextResponse } from "next/server";

const cookieName = "laos_session";

export async function POST(request: NextRequest) {
  const origin = request.headers.get("origin");
  if (origin && origin !== request.nextUrl.origin) {
    return NextResponse.json({ message: "Permintaan lintas situs ditolak." }, { status: 403 });
  }

  const token = request.cookies.get(cookieName)?.value;
  const baseUrl = process.env.LAOS_API_BASE_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL;
  let upstreamFailure = Boolean(token && !baseUrl);
  if (token && baseUrl) {
    try {
      const response = await fetch(`${baseUrl.replace(/\/+$/, "")}/auth/logout`, {
        method: "POST",
        cache: "no-store",
        headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      });
      upstreamFailure = !response.ok;
    } catch (error) {
      console.error("Unable to reach the LAOS API during logout.", error);
      upstreamFailure = true;
    }
  }

  const result = NextResponse.json(
    { message: upstreamFailure ? "Sesi lokal diakhiri; pencabutan token API belum terkonfirmasi." : "Sesi berhasil diakhiri." },
    { status: upstreamFailure ? 502 : 200 },
  );
  result.cookies.delete(cookieName);
  return result;
}
