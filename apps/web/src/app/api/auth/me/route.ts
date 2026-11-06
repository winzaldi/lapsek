import { NextRequest, NextResponse } from "next/server";

const cookieName = "laos_session";

export async function GET(request: NextRequest) {
  const token = request.cookies.get(cookieName)?.value;
  if (!token) return NextResponse.json({ authenticated: false });

  const baseUrl = process.env.LAOS_API_BASE_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL;
  if (!baseUrl) {
    return NextResponse.json({ message: "Alamat API belum dikonfigurasi." }, { status: 500 });
  }

  try {
    const response = await fetch(`${baseUrl.replace(/\/+$/, "")}/auth/me`, {
      cache: "no-store",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
    });
    const payload = await response.json().catch(() => null);
    const result = NextResponse.json(
      response.ok ? { ...(payload as object), authenticated: true } : payload,
      { status: response.status },
    );
    if (response.status === 401) result.cookies.delete(cookieName);
    return result;
  } catch (error) {
    console.error("Unable to reach the LAOS API while checking the session.", error);
    return NextResponse.json({ message: "Layanan API tidak dapat dihubungi." }, { status: 502 });
  }
}
