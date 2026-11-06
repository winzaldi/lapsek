import { NextRequest, NextResponse } from "next/server";

const cookieName = "laos_session";

export async function POST(request: NextRequest) {
  const origin = request.headers.get("origin");
  if (origin && origin !== request.nextUrl.origin) {
    return NextResponse.json({ message: "Permintaan lintas situs ditolak." }, { status: 403 });
  }

  let credentials: unknown;
  try {
    credentials = await request.json();
  } catch {
    return NextResponse.json({ message: "Format permintaan tidak valid." }, { status: 400 });
  }
  if (
    !credentials
    || typeof credentials !== "object"
    || typeof (credentials as Record<string, unknown>).email !== "string"
    || typeof (credentials as Record<string, unknown>).password !== "string"
  ) {
    return NextResponse.json({ message: "Email dan kata sandi wajib diisi." }, { status: 422 });
  }
  const { email, password } = credentials as { email: string; password: string };
  const baseUrl = process.env.LAOS_API_BASE_URL ?? process.env.NEXT_PUBLIC_API_BASE_URL;
  if (!baseUrl) {
    return NextResponse.json({ message: "Alamat API belum dikonfigurasi." }, { status: 500 });
  }

  try {
    const response = await fetch(`${baseUrl.replace(/\/+$/, "")}/auth/login`, {
      method: "POST",
      cache: "no-store",
      headers: { Accept: "application/json", "Content-Type": "application/json" },
      body: JSON.stringify({
        email,
        password,
        device_name: "LAOS Web",
      }),
    });
    const payload = await response.json().catch(() => null) as {
      access_token?: string;
      expires_in?: number;
      user?: unknown;
      message?: string;
      errors?: Record<string, string[]>;
    } | null;

    if (!response.ok) {
      return NextResponse.json(
        { message: payload?.message ?? "Email atau kata sandi tidak valid.", errors: payload?.errors },
        { status: response.status },
      );
    }
    if (!payload?.access_token || !payload.user) {
      console.error("LAOS API login response was missing required session fields.");
      return NextResponse.json({ message: "Respons autentikasi API tidak lengkap." }, { status: 502 });
    }

    const result = NextResponse.json({ user: payload.user });
    result.cookies.set(cookieName, payload.access_token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "strict",
      path: "/",
      maxAge: Math.max(60, payload.expires_in ?? 3600),
    });
    return result;
  } catch (error) {
    console.error("Unable to reach the LAOS API during login.", error);
    return NextResponse.json({ message: "Layanan API tidak dapat dihubungi." }, { status: 502 });
  }
}
