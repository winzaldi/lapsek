import Link from "next/link";

export default function Home() {
  return (
    <main className="shell">
      <header className="topbar">
        <Link aria-label="LAOS beranda" className="brand" href="/">
          <span aria-hidden="true" className="brand-mark">L</span>
          <span>LAOS</span>
        </Link>
        <span className="environment">Fondasi aplikasi</span>
      </header>

      <section aria-labelledby="page-title" className="welcome">
        <div className="eyebrow">Laporan Online Sekolah</div>
        <h1 id="page-title">Fondasi sistem siap dikembangkan.</h1>
        <p>
          Platform pencatatan dan laporan kesehatan UKS untuk Puskesmas,
          sekolah, dan siswa. Fitur operasional akan dibangun setelah fondasi
          teknis dan aturan akses disepakati.
        </p>
        <div className="status-card">
          <span aria-hidden="true" className="status-dot" />
          <div>
            <strong>Lingkungan web aktif</strong>
            <span>Belum terhubung ke data siswa atau hasil pemeriksaan.</span>
          </div>
        </div>
      </section>

      <footer className="footer">
        <span>LAOS · Data kesehatan UKS</span>
        <span>Lingkungan pengembangan</span>
      </footer>
    </main>
  );
}
