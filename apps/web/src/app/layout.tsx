import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "LAOS — Laporan Online Sekolah",
  description:
    "Fondasi aplikasi Laporan Online Sekolah untuk data kesehatan UKS.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
