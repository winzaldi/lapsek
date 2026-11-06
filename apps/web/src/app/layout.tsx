import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "LAOS — Laporan Online Sekolah",
  description:
    "Ruang kerja pemeriksaan kesehatan UKS untuk Puskesmas dan sekolah.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}
