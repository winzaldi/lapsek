"use client";

import { FormEvent, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { apiFetch, formatApiError, rowsFrom } from "@/lib/api";
import AdminWorkspace, { AdminTab } from "@/components/admin-workspace";
import { ReportsPanel, SchoolWorkspace, StudentWorkspace } from "@/components/role-workspaces";
import {
  indicators,
  normalizeObservations,
  Observation,
  Observations,
} from "@/lib/indicators";

type Role = { name: string; label: string };
type User = { id: number; name: string; email: string; roles: Role[] };
type Session = { user: User; student_id?: number | null };
type School = { id: number; name: string; level: string; npsn?: string | null };
type AcademicYear = { id: number; school_id: number; name: string; is_active: boolean };
type Semester = { id: number; academic_year_id: number; number: number; starts_on: string; ends_on: string };
type SchoolClass = { id: number; school_id: number; name: string; grade: number; academic_level: string; is_active: boolean };
type Student = { id: number; name: string; nisn?: string | null; birth_date?: string | null; sex?: string | null };
type CoverageRow = {
  student: Student;
  school: School;
  school_class: SchoolClass | null;
  examination_id: number | null;
  examination_status: string | null;
  coverage_status: "checked" | "not_checked";
};
type Examination = {
  id: number;
  student: Student;
  school: School;
  school_class: { id: number; name: string } | null;
  semester: Semester;
  examined_at: string;
  examiner_name: string;
  status: "draft" | "finalized" | "published";
  version: number;
  bmi?: string | null;
  observations: Record<string, { status: string; value: unknown }>;
};
type Paginator<T> = { data: T[] };
type CoverageResponse = { summary?: { total: number; checked: number; not_checked: number; coverage_percent: number }; data?: Paginator<CoverageRow> };

const roleTitles: Record<string, string> = {
  system_admin: "Admin Sistem",
  puskesmas_operator: "Operator Puskesmas",
  school_operator: "Operator Sekolah / Guru",
  student: "Siswa",
};

function roleOf(user: User) {
  return user.roles[0]?.name ?? "";
}

function today() {
  const date = new Date();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${date.getFullYear()}-${month}-${day}`;
}

function semesterTitle(semester: Semester, years: AcademicYear[]) {
  const year = years.find((item) => item.id === semester.academic_year_id);
  return `${year?.name ?? "Tahun ajaran"} · Semester ${semester.number}`;
}

function statusTitle(status: Examination["status"]) {
  return { draft: "Draf", finalized: "Final", published: "Terpublikasi" }[status];
}

export default function Dashboard() {
  const [session, setSession] = useState<Session | null>(null);
  const [checkingSession, setCheckingSession] = useState(true);
  const [schools, setSchools] = useState<School[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [semesters, setSemesters] = useState<Semester[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [schoolId, setSchoolId] = useState("");
  const [semesterId, setSemesterId] = useState("");
  const [coverage, setCoverage] = useState<CoverageRow[]>([]);
  const [coverageSummary, setCoverageSummary] = useState<CoverageResponse["summary"]>();
  const [examinations, setExaminations] = useState<Examination[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [activeTab, setActiveTab] = useState<"overview" | "examinations" | "students" | Exclude<AdminTab, "overview">>("overview");
  const [editor, setEditor] = useState<{ record?: Examination; student?: Student; mode: "draft" | "correction" } | null>(null);
  const [report, setReport] = useState<Examination | null>(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");
  const [search, setSearch] = useState("");
  const [operationsRefreshKey, setOperationsRefreshKey] = useState(0);

  const role = session ? roleOf(session.user) : "";
  const isPuskesmas = role === "puskesmas_operator";
  const canManageStudents = role === "puskesmas_operator" || role === "school_operator";
  const selectedSchool = schools.find((school) => String(school.id) === schoolId);
  const schoolYears = useMemo(
    () => years.filter((year) => String(year.school_id) === schoolId),
    [years, schoolId],
  );
  const schoolSemesters = useMemo(
    () => semesters.filter((semester) =>
      schoolYears.some((year) => year.id === semester.academic_year_id),
    ),
    [semesters, schoolYears],
  );
  const selectedSemester = schoolSemesters.find((semester) => String(semester.id) === semesterId);

  useEffect(() => {
    let active = true;
    fetch("/api/auth/me", { cache: "no-store" })
      .then(async (response) => {
        if (!active) return;
        if (!response.ok) {
          setSession(null);
          return;
        }
        const payload = await response.json() as Session & { authenticated?: boolean };
        setSession(payload.authenticated && payload.user ? payload : null);
      })
      .catch(() => {
        if (active) setMessage("Tidak dapat memeriksa sesi. Periksa koneksi aplikasi.");
      })
      .finally(() => {
        if (active) setCheckingSession(false);
      });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!session || role === "system_admin" || role === "student") return;
    let active = true;
    Promise.all([
      apiFetch<unknown>("schools"),
      apiFetch<unknown>("academic-years"),
      apiFetch<unknown>("semesters"),
    ]).then(([schoolResponse, yearResponse, semesterResponse]) => {
      if (!active) return;
      const nextSchools = rowsFrom<School>(schoolResponse);
      const nextYears = rowsFrom<AcademicYear>(yearResponse);
      const nextSemesters = rowsFrom<Semester>(semesterResponse);
      setSchools(nextSchools);
      setYears(nextYears);
      setSemesters(nextSemesters);
      if (nextSchools.length) {
        const nextSchool = nextSchools[0];
        const yearIds = nextYears.filter((year) => year.school_id === nextSchool.id).map((year) => year.id);
        const latestSemester = nextSemesters
          .filter((semester) => yearIds.includes(semester.academic_year_id))
          .sort((a, b) => b.starts_on.localeCompare(a.starts_on))[0];
        setSchoolId(String(nextSchool.id));
        setSemesterId(latestSemester ? String(latestSemester.id) : "");
      }
    }).catch((error: unknown) => setMessage(formatApiError(error)))
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [session, role]);

  useEffect(() => {
    if (!session || !schoolId || !semesterId || role === "student" || role === "system_admin") return;
    let active = true;
    const query = new URLSearchParams({ school_id: schoolId, semester_id: semesterId });
    const operations: Promise<unknown>[] = [
      apiFetch<unknown>(`examinations?${query.toString()}`),
    ];
    if (isPuskesmas) {
      operations.push(apiFetch<CoverageResponse>(`examination-coverage?${query.toString()}`));
    }
    Promise.all(operations).then((results) => {
      if (!active) return;
      setExaminations(rowsFrom<Examination>(results[0]));
      if (isPuskesmas) {
        const coverageResult = results[1] as CoverageResponse;
        setCoverage(coverageResult.data?.data ?? []);
        setCoverageSummary(coverageResult.summary);
      }
    }).catch((error: unknown) => {
      if (!active) return;
      setMessage(formatApiError(error));
      setExaminations([]);
      setCoverage([]);
    }).finally(() => {
      if (active) setLoading(false);
    });
    return () => { active = false; };
  }, [session, schoolId, semesterId, role, isPuskesmas, operationsRefreshKey]);

  useEffect(() => {
    if (!session || !schoolId || activeTab !== "students" || !canManageStudents) return;
    let active = true;
    const query = new URLSearchParams({ school_id: schoolId });
    Promise.all([
      apiFetch<unknown>(`students?${query.toString()}`),
      apiFetch<unknown>(`classes?${query.toString()}`),
    ]).then(([studentResponse, classResponse]) => {
      if (!active) return;
      setStudents(rowsFrom<Student>(studentResponse));
      setClasses(rowsFrom<SchoolClass>(classResponse));
    }).catch((error: unknown) => setMessage(formatApiError(error)))
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [session, schoolId, activeTab, canManageStudents]);

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setMessage("");
    const form = new FormData(event.currentTarget);
    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ email: form.get("email"), password: form.get("password") }),
      });
      const payload = await response.json() as { user?: User; message?: string };
      if (!response.ok || !payload.user) throw new Error(payload.message ?? "Login tidak berhasil.");
      const sessionResponse = await fetch("/api/auth/me", { cache: "no-store" });
      const authenticatedSession = await sessionResponse.json() as Session & { authenticated?: boolean };
      if (!sessionResponse.ok || !authenticatedSession.authenticated || !authenticatedSession.user) {
        throw new Error("Login berhasil, tetapi sesi pengguna tidak dapat dimuat.");
      }
      setSession(authenticatedSession);
      setActiveTab("overview");
      setMessage("");
    } catch (error) {
      setMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function logout() {
    setBusy(true);
    try {
      const response = await fetch("/api/auth/logout", { method: "POST" });
      if (!response.ok) {
        const payload = await response.json().catch(() => null);
        setMessage(payload?.message ?? "Sesi lokal diakhiri, tetapi server tidak mengonfirmasi logout.");
      } else {
        setMessage("");
      }
    } catch {
      setMessage("Tidak dapat menghubungi server saat logout. Sesi lokal telah diakhiri.");
    } finally {
      setSession(null);
      setBusy(false);
    }
  }

  async function runExaminationAction(examination: Examination, action: "finalize" | "publish") {
    const label = action === "finalize" ? "finalisasi" : "publikasi";
    if (!window.confirm(`Lanjutkan ${label} pemeriksaan ${examination.student.name}?`)) return;
    setBusy(true);
    setLoading(true);
    setMessage("");
    try {
      await apiFetch(`examinations/${examination.id}/${action}`, { method: "POST", body: "{}" });
      setOperationsRefreshKey((current) => current + 1);
    } catch (error) {
      setMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function createClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedSchool) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setBusy(true);
    setMessage("");
    try {
      await apiFetch("classes", {
        method: "POST",
        body: JSON.stringify({
          school_id: selectedSchool.id,
          name: form.get("class_name"),
          grade: Number(form.get("grade")),
          academic_level: selectedSchool.level,
        }),
      });
      setClasses(rowsFrom(await apiFetch<unknown>(`classes?school_id=${selectedSchool.id}`)));
      formElement.reset();
    } catch (error) {
      setMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function createStudent(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedSchool || !schoolYears.length) return;
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    setBusy(true);
    setMessage("");
    try {
      await apiFetch("students", {
        method: "POST",
        body: JSON.stringify({
          school_id: selectedSchool.id,
          school_class_id: form.get("school_class_id") ? Number(form.get("school_class_id")) : null,
          academic_year_id: Number(form.get("academic_year_id")),
          name: form.get("student_name"),
          nisn: form.get("nisn") || null,
          birth_date: form.get("birth_date") || null,
          sex: form.get("sex") || null,
          starts_on: form.get("starts_on"),
        }),
      });
      setStudents(rowsFrom(await apiFetch<unknown>(`students?school_id=${selectedSchool.id}`)));
      formElement.reset();
      setMessage("Data siswa dan enrollment berhasil ditambahkan.");
    } catch (error) {
      setMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  const filteredExaminations = useMemo(() => {
    const normalized = search.trim().toLocaleLowerCase("id");
    if (!normalized) return examinations;
    return examinations.filter((item) =>
      item.student.name.toLocaleLowerCase("id").includes(normalized)
      || (item.student.nisn ?? "").includes(normalized),
    );
  }, [examinations, search]);

  const filteredStudents = useMemo(() => {
    const normalized = search.trim().toLocaleLowerCase("id");
    if (!normalized) return students;
    return students.filter((item) =>
      item.name.toLocaleLowerCase("id").includes(normalized)
      || (item.nisn ?? "").includes(normalized),
    );
  }, [students, search]);

  function changeSchool(value: string) {
    setSchoolId(value);
    const yearIds = years.filter((year) => String(year.school_id) === value).map((year) => year.id);
    const latestSemester = semesters
      .filter((semester) => yearIds.includes(semester.academic_year_id))
      .sort((a, b) => b.starts_on.localeCompare(a.starts_on))[0];
    setSemesterId(latestSemester ? String(latestSemester.id) : "");
    setLoading(true);
  }

  function refreshOperations() {
    setMessage("");
    setLoading(true);
    setOperationsRefreshKey((current) => current + 1);
  }

  if (checkingSession) {
    return <main className="loading-screen"><span className="spinner" /> Memeriksa sesi...</main>;
  }
  if (!session) {
    return <LoginPanel busy={busy} message={message} onSubmit={handleLogin} />;
  }
  if (role === "student") {
    return <StudentWorkspace session={session} onLogout={() => void logout()} />;
  }
  if (role === "school_operator") {
    return <SchoolWorkspace user={session.user} onLogout={() => void logout()} />;
  }

  const title = roleTitles[role] ?? "Pengguna LAOS";
  const supportedRole = role === "system_admin" || role === "puskesmas_operator" || role === "school_operator";
  const filteredCoverage = coverage.filter((row) =>
    !search.trim()
    || row.student.name.toLocaleLowerCase("id").includes(search.trim().toLocaleLowerCase("id"))
    || (row.student.nisn ?? "").includes(search.trim()),
  );

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link className="brand" href="/" aria-label="LAOS beranda">
          <span className="brand-mark">L</span>
          <span>LAOS<span className="brand-subtitle">LAYANAN KESEHATAN SEKOLAH</span></span>
        </Link>
        <div className="side-label">RUANG KERJA</div>
        <nav className="side-nav" aria-label="Navigasi utama">
          {role === "system_admin" ? (
            ([
              ["overview", "◫", "Ringkasan"],
              ["centers", "✚", "Puskesmas"],
              ["schools", "⌂", "Sekolah"],
              ["periods", "◷", "Tahun ajaran & semester"],
              ["accounts", "♧", "Akun & penugasan"],
              ["students", "▦", "Siswa & kelas"],
              ["reports", "▤", "Laporan"],
            ] as const).map(([tab, icon, label]) => (
              <button className={activeTab === tab ? "nav-item active" : "nav-item"} key={tab} onClick={() => setActiveTab(tab)}>
                <span>{icon}</span> {label}
              </button>
            ))
          ) : (
            <>
              <button className={activeTab === "overview" ? "nav-item active" : "nav-item"} onClick={() => setActiveTab("overview")}>
                <span>◫</span> Ringkasan
              </button>
              <button className={activeTab === "examinations" ? "nav-item active" : "nav-item"} onClick={() => setActiveTab("examinations")}>
                <span>＋</span> Pemeriksaan
              </button>
              {canManageStudents && (
                <button className={activeTab === "students" ? "nav-item active" : "nav-item"} onClick={() => { setActiveTab("students"); setSearch(""); setLoading(true); }}>
                  <span>♧</span> Data siswa
                </button>
              )}
              {isPuskesmas && (
                <button className={activeTab === "reports" ? "nav-item active" : "nav-item"} onClick={() => setActiveTab("reports")}>
                  <span>▤</span> Laporan
                </button>
              )}
            </>
          )}
        </nav>
        <div className="sidebar-spacer" />
        <div className="privacy-note"><span className="privacy-icon">◈</span><strong>Data terlindungi</strong><span>Akses mengikuti peran dan sekolah yang ditugaskan.</span></div>
        <div className="sidebar-footer">LAOS · UKS DIGITAL</div>
      </aside>

      <main className="main-area">
        <header className="top-header">
          <div className="breadcrumbs"><span>Ruang kerja</span><b>/</b><strong>{activeTab === "overview" ? "Ringkasan" : activeTab === "students" ? "Data siswa" : activeTab === "centers" ? "Puskesmas" : activeTab === "schools" ? "Sekolah" : activeTab === "periods" ? "Tahun ajaran & semester" : activeTab === "accounts" ? "Akun & penugasan" : activeTab === "reports" ? "Laporan" : "Pemeriksaan"}</strong></div>
          <div className="account">
            <span className="avatar">{session.user.name.slice(0, 1).toLocaleUpperCase("id")}</span>
            <div className="account-name"><strong>{session.user.name}</strong><span>{title}</span></div>
            <button className="text-button" onClick={() => void logout()} disabled={busy}>Keluar</button>
          </div>
        </header>

        <div className="page-content">
          {!supportedRole ? (
            <section className="empty-state card">
              <div className="empty-icon">◈</div>
              <h1>Ruang kerja belum tersedia</h1>
              <p>Akun {title} berhasil masuk, tetapi belum ada modul operasional untuk peran ini.</p>
            </section>
          ) : role === "system_admin" ? (
            <>
              {message && <div className="alert" role="alert"><span>!</span><p>{message}</p><button aria-label="Tutup pesan" onClick={() => setMessage("")}>×</button></div>}
              {activeTab === "reports" ? <ReportsPanel roleName={role} /> : <AdminWorkspace
                tab={activeTab === "overview" || activeTab === "centers" || activeTab === "schools" || activeTab === "periods" || activeTab === "accounts" || activeTab === "students" ? activeTab : "overview"}
                onMessage={setMessage}
                onNavigate={setActiveTab}
              />}
            </>
          ) : activeTab === "reports" ? (
            <ReportsPanel roleName={role} />
          ) : (
            <>
              <section className="page-heading">
                <div>
                  <div className="eyebrow">{isPuskesmas ? "PUSKESMAS · OPERASIONAL UKS" : "SEKOLAH · PEMANTAUAN UKS"}</div>
                  <h1>{activeTab === "overview" ? "Ringkasan pemeriksaan" : activeTab === "students" ? "Data siswa" : "Pemeriksaan kesehatan"}</h1>
                  <p>{isPuskesmas ? "Kelola pemeriksaan siswa di sekolah binaan Anda." : "Pantau hasil pemeriksaan yang telah dipublikasikan untuk sekolah Anda."}</p>
                </div>
                <button className="button button-quiet" onClick={refreshOperations} disabled={loading || !semesterId}>
                  <span aria-hidden="true">↻</span> Perbarui data
                </button>
              </section>

              {message && <div className="alert" role="alert"><span>!</span><p>{message}</p><button aria-label="Tutup pesan" onClick={() => setMessage("")}>×</button></div>}

              <section className="filter-bar card">
                <label className="filter-field">
                  <span>Sekolah</span>
                  <select value={schoolId} onChange={(event) => changeSchool(event.target.value)} disabled={schools.length < 2}>
                    {schools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}
                  </select>
                </label>
                <label className="filter-field">
                  <span>Tahun ajaran · semester</span>
                  <select value={semesterId} onChange={(event) => { setSemesterId(event.target.value); setLoading(true); }} disabled={!schoolSemesters.length}>
                    {schoolSemesters.map((semester) => <option key={semester.id} value={semester.id}>{semesterTitle(semester, years)}</option>)}
                  </select>
                </label>
                <label className="filter-field search-field">
                  <span>Cari siswa</span>
                  <span className="search-control"><span aria-hidden="true">⌕</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau NISN" /></span>
                </label>
              </section>

              {!schools.length && !loading ? (
                <section className="empty-state card"><div className="empty-icon">⌂</div><h2>Belum ada sekolah dalam cakupan</h2><p>Hubungi Admin Sistem untuk memeriksa penugasan akun dan data sekolah.</p></section>
              ) : !schoolSemesters.length && !loading ? (
                <section className="empty-state card"><div className="empty-icon">◷</div><h2>Semester belum tersedia</h2><p>Belum ada periode pemeriksaan untuk sekolah ini. Hubungi pengelola sistem.</p></section>
              ) : (
                <>
                  {activeTab === "overview" && (
                    <>
                      <section className="stat-grid">
                        <StatCard label="Sasaran semester" value={isPuskesmas ? coverageSummary?.total : examinations.length} note={selectedSemester ? semesterTitle(selectedSemester, years) : "Pilih periode"} icon="◎" />
                        <StatCard label={isPuskesmas ? "Sudah diperiksa" : "Hasil tersedia"} value={isPuskesmas ? coverageSummary?.checked : examinations.length} note={isPuskesmas ? "Termasuk draf dan hasil final" : "Hasil yang sudah dipublikasikan"} icon="✓" />
                        {isPuskesmas ? (
                          <StatCard label="Belum diperiksa" value={coverageSummary?.not_checked} note="Siswa terdaftar dalam periode" icon="◷" />
                        ) : (
                          <StatCard label="Sekolah" value={selectedSchool?.name ?? "—"} note={selectedSchool?.npsn ? `NPSN ${selectedSchool.npsn}` : "Dalam cakupan akun"} icon="⌂" />
                        )}
                        <StatCard label="Cakupan" value={isPuskesmas ? `${coverageSummary?.coverage_percent ?? 0}%` : `${examinations.length} hasil`} note={isPuskesmas ? "Dari seluruh sasaran semester" : "Riwayat periode terpilih"} icon="↗" />
                      </section>
                      <div className="section-heading">
                        <div><h2>{isPuskesmas ? "Siswa belum diperiksa" : "Pemeriksaan terbaru"}</h2><p>{isPuskesmas ? "Pilih siswa untuk mulai mengisi formulir pemeriksaan." : "Hanya hasil yang telah dipublikasikan yang dapat dilihat."}</p></div>
                        <button className="button button-quiet" onClick={() => setActiveTab("examinations")}>Lihat semua <span>→</span></button>
                      </div>
                      {isPuskesmas ? (
                        <CoverageTable rows={filteredCoverage.filter((row) => row.coverage_status === "not_checked").slice(0, 8)} loading={loading} onCreate={(student) => setEditor({ student, mode: "draft" })} />
                      ) : (
                        <ExaminationTable items={filteredExaminations.slice(0, 8)} loading={loading} isPuskesmas={false} busy={busy} onView={setReport} onEdit={() => undefined} onAction={() => undefined} onCorrect={() => undefined} />
                      )}
                    </>
                  )}

                  {activeTab === "examinations" && (
                    <>
                      {isPuskesmas && (
                        <section className="coverage-banner card">
                          <div><span className="banner-icon">◎</span><div><strong>{coverageSummary?.checked ?? 0} dari {coverageSummary?.total ?? 0} siswa telah diperiksa</strong><span>{coverageSummary?.not_checked ?? 0} sasaran masih menunggu pemeriksaan pada periode ini.</span></div></div>
                          <div className="progress-track" aria-label={`Cakupan ${coverageSummary?.coverage_percent ?? 0}%`}><span style={{ width: `${Math.min(coverageSummary?.coverage_percent ?? 0, 100)}%` }} /></div>
                        </section>
                      )}
                      {isPuskesmas && (
                        <section className="section-heading compact-heading"><div><h2>Daftar sasaran</h2><p>Pemeriksaan per siswa pada {selectedSemester ? semesterTitle(selectedSemester, years) : "semester terpilih"}.</p></div></section>
                      )}
                      {isPuskesmas ? (
                        <>
                          <CoverageTable rows={filteredCoverage} loading={loading} onCreate={(student) => setEditor({ student, mode: "draft" })} />
                          <section className="card table-card examination-list">
                            <div className="table-heading"><div><h2>Arsip pemeriksaan</h2><span>Draf, hasil final, dan pemeriksaan terpublikasi.</span></div><span className="record-count">{filteredExaminations.length} pemeriksaan</span></div>
                            <ExaminationTable
                              items={filteredExaminations}
                              loading={loading}
                              isPuskesmas
                              busy={busy}
                              onView={setReport}
                              onEdit={(item) => setEditor({ record: item, mode: "draft" })}
                              onAction={(item, action) => void runExaminationAction(item, action)}
                              onCorrect={(item) => setEditor({ record: item, mode: "correction" })}
                            />
                          </section>
                        </>
                      ) : (
                        <section className="card table-card">
                          <div className="table-heading"><div><h2>Hasil pemeriksaan</h2><span>{filteredExaminations.length} hasil pada periode terpilih</span></div></div>
                          <ExaminationTable items={filteredExaminations} loading={loading} isPuskesmas={false} busy={busy} onView={setReport} onEdit={() => undefined} onAction={() => undefined} onCorrect={() => undefined} />
                        </section>
                      )}
                    </>
                  )}

                  {activeTab === "students" && canManageStudents && (
                    <section className="student-workspace">
                      <div className="student-grid">
                        <section className="card form-card">
                          <div className="card-title"><span className="title-icon">♧</span><div><h2>Tambah siswa</h2><p>Data akan ditautkan ke sekolah dan tahun ajaran aktif.</p></div></div>
                          <form className="stack-form" onSubmit={(event) => void createStudent(event)}>
                            <label>Nama lengkap<input name="student_name" required maxLength={255} /></label>
                            <div className="form-row">
                              <label>NISN <span className="optional">opsional</span><input name="nisn" maxLength={20} /></label>
                              <label>Tanggal lahir <span className="optional">opsional</span><input name="birth_date" type="date" max={today()} /></label>
                            </div>
                            <div className="form-row">
                              <label>Jenis kelamin <span className="optional">opsional</span><select name="sex" defaultValue=""><option value="">Pilih</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select></label>
                              <label>Kelas<select name="school_class_id" defaultValue=""><option value="">Belum ditentukan</option>{classes.filter((item) => item.is_active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                            </div>
                            <div className="form-row">
                              <label>Tahun ajaran<select name="academic_year_id" required defaultValue=""><option value="">Pilih tahun ajaran</option>{schoolYears.map((year) => <option key={year.id} value={year.id}>{year.name}{year.is_active ? " · Aktif" : ""}</option>)}</select></label>
                              <label>Mulai terdaftar<input name="starts_on" type="date" required defaultValue={today()} /></label>
                            </div>
                            <button className="button button-primary" disabled={busy || !schoolYears.length}>Simpan siswa</button>
                          </form>
                        </section>
                        <section className="card form-card">
                          <div className="card-title"><span className="title-icon violet">▦</span><div><h2>Tambah kelas</h2><p>Kelas baru mengikuti jenjang {selectedSchool?.level?.toUpperCase() ?? "sekolah"}.</p></div></div>
                          <form className="stack-form" onSubmit={(event) => void createClass(event)}>
                            <label>Nama kelas<input name="class_name" required maxLength={64} placeholder="Contoh: 4-A" /></label>
                            <label>Tingkat kelas<input name="grade" type="number" min={1} max={12} required /></label>
                            <button className="button button-outline" disabled={busy || !selectedSchool}>Simpan kelas</button>
                          </form>
                          <div className="class-list"><strong>Kelas terdaftar</strong>{classes.length ? classes.map((item) => <span key={item.id}>{item.name}<small>Tingkat {item.grade}</small></span>) : <p>Belum ada kelas.</p>}</div>
                        </section>
                      </div>
                      <section className="card table-card">
                        <div className="table-heading"><div><h2>Daftar siswa</h2><span>Menampilkan siswa dalam cakupan sekolah.</span></div><span className="record-count">{filteredStudents.length} siswa</span></div>
                        <div className="table-scroll"><table><thead><tr><th>Nama siswa</th><th>NISN</th><th>Tanggal lahir</th></tr></thead><tbody>
                          {loading ? <TableMessage>Memuat data siswa...</TableMessage> : filteredStudents.length ? filteredStudents.map((student) => <tr key={student.id}><td><strong>{student.name}</strong></td><td>{student.nisn ?? "—"}</td><td>{student.birth_date ?? "—"}</td></tr>) : <TableMessage>Belum ada siswa yang cocok dengan pencarian.</TableMessage>}
                        </tbody></table></div>
                      </section>
                    </section>
                  )}
                </>
              )}
            </>
          )}
        </div>
        <footer className="app-footer"><span>LAOS · Laporan Online Sekolah</span><span>Data kesehatan siswa · Akses terbatas</span></footer>
      </main>

      {editor && (
        <ExaminationEditor
          key={`${editor.mode}-${editor.record?.id ?? editor.student?.id ?? "new"}`}
          mode={editor.mode}
          record={editor.record}
          student={editor.student}
          user={session.user}
          semester={selectedSemester}
          onCancel={() => setEditor(null)}
          onSaved={async () => { setEditor(null); setLoading(true); setOperationsRefreshKey((current) => current + 1); }}
        />
      )}
      {report && <ReportModal examination={report} onClose={() => setReport(null)} />}
    </div>
  );
}

function LoginPanel({ busy, message, onSubmit }: { busy: boolean; message: string; onSubmit: (event: FormEvent<HTMLFormElement>) => void }) {
  return (
    <main className="login-shell">
      <section className="login-brand-panel">
        <Link className="brand brand-light" href="/"><span className="brand-mark">L</span><span>LAOS<span className="brand-subtitle">LAYANAN KESEHATAN SEKOLAH</span></span></Link>
        <div className="login-hero">
          <div className="hero-orbit orbit-one" /><div className="hero-orbit orbit-two" />
          <div className="hero-symbol">+</div>
          <div className="hero-copy"><span>SEHAT BERSAMA</span><h1>Setiap anak berhak tumbuh sehat.</h1><p>Arsip pemeriksaan UKS yang terhubung antara Puskesmas, sekolah, dan keluarga.</p></div>
        </div>
        <div className="login-panel-footer">PLATFORM KESEHATAN SEKOLAH TERPADU <span>·</span> INDONESIA</div>
      </section>
      <section className="login-form-panel">
        <div className="login-form-wrap">
          <div className="mobile-login-brand"><span className="brand-mark">L</span> LAOS</div>
          <div className="eyebrow">SELAMAT DATANG KEMBALI</div>
          <h2>Masuk ke akun Anda</h2>
          <p>Gunakan email dan kata sandi yang diberikan oleh Admin Sistem.</p>
          {message && <div className="alert" role="alert"><span>!</span><p>{message}</p></div>}
          <form className="login-form" onSubmit={onSubmit}>
            <label htmlFor="email">Alamat email</label>
            <input id="email" name="email" type="email" autoComplete="username" required placeholder="nama@instansi.go.id" />
            <label htmlFor="password">Kata sandi</label>
            <input id="password" name="password" type="password" autoComplete="current-password" required />
            <button className="button button-primary login-submit" disabled={busy}>{busy ? <><span className="spinner small" /> Memeriksa...</> : <>Masuk ke LAOS <span>→</span></>}</button>
          </form>
          <div className="login-security"><span>◈</span><p>Sesi login dilindungi dan hanya memberikan akses sesuai peran serta cakupan sekolah Anda.</p></div>
          <div className="login-help">Butuh bantuan? Hubungi Admin Sistem di instansi Anda.</div>
        </div>
      </section>
    </main>
  );
}

function StatCard({ label, value, note, icon }: { label: string; value?: string | number; note: string; icon: string }) {
  return <article className="stat-card card"><div className="stat-top"><span>{label}</span><span className="stat-icon">{icon}</span></div><strong>{value ?? "—"}</strong><small>{note}</small></article>;
}

function CoverageTable({ rows, loading, onCreate }: { rows: CoverageRow[]; loading: boolean; onCreate: (student: Student) => void }) {
  return (
    <section className="card table-card">
      <div className="table-scroll"><table><thead><tr><th>Siswa</th><th>Kelas</th><th>Status</th><th><span className="sr-only">Aksi</span></th></tr></thead><tbody>
        {loading ? <TableMessage>Memuat sasaran...</TableMessage> : rows.length ? rows.map((row) => (
          <tr key={`${row.student.id}-${row.examination_id ?? "none"}`}>
            <td><div className="student-cell"><span className="student-avatar">{row.student.name.slice(0, 1).toLocaleUpperCase("id")}</span><div><strong>{row.student.name}</strong><small>{row.student.nisn ? `NISN ${row.student.nisn}` : "NISN belum diisi"}</small></div></div></td>
            <td>{row.school_class?.name ?? "—"}</td>
            <td><span className={`status-pill ${row.coverage_status === "checked" ? "status-done" : "status-waiting"}`}><i />{row.coverage_status === "checked" ? statusTitle(row.examination_status as Examination["status"]) : "Belum diperiksa"}</span></td>
            <td className="actions-cell">{row.coverage_status === "not_checked" && <button className="button button-small button-outline" onClick={() => onCreate(row.student)}>Mulai pemeriksaan <span>→</span></button>}</td>
          </tr>
        )) : <TableMessage>Tidak ada sasaran untuk filter yang dipilih.</TableMessage>}
      </tbody></table></div>
      <div className="table-foot"><span>Data siswa dari enrollment semester terpilih</span><span>Menampilkan {rows.length} siswa</span></div>
    </section>
  );
}

function ExaminationTable({
  items, loading, isPuskesmas, busy, onView, onEdit, onAction, onCorrect,
}: {
  items: Examination[];
  loading: boolean;
  isPuskesmas: boolean;
  busy: boolean;
  onView: (examination: Examination) => void;
  onEdit: (examination: Examination) => void;
  onAction: (examination: Examination, action: "finalize" | "publish") => void;
  onCorrect: (examination: Examination) => void;
}) {
  return (
    <div className="table-scroll"><table><thead><tr><th>Siswa</th><th>Tanggal</th><th>Pemeriksa</th><th>Status</th><th><span className="sr-only">Aksi</span></th></tr></thead><tbody>
      {loading ? <TableMessage>Memuat pemeriksaan...</TableMessage> : items.length ? items.map((item) => (
        <tr key={item.id}>
          <td><div className="student-cell"><span className="student-avatar">{item.student.name.slice(0, 1).toLocaleUpperCase("id")}</span><div><strong>{item.student.name}</strong><small>{item.student.nisn ? `NISN ${item.student.nisn}` : item.school_class?.name ?? "Siswa"}</small></div></div></td>
          <td>{item.examined_at}</td><td>{item.examiner_name}</td>
          <td><span className={`status-pill status-${item.status}`}><i />{statusTitle(item.status)}</span></td>
          <td className="actions-cell">
            {isPuskesmas && item.status === "draft" && <><button className="table-action" onClick={() => onEdit(item)}>Ubah</button><button className="table-action" disabled={busy} onClick={() => onAction(item, "finalize")}>Finalisasi</button></>}
            {isPuskesmas && item.status === "finalized" && <button className="table-action" disabled={busy} onClick={() => onAction(item, "publish")}>Publikasikan</button>}
            {isPuskesmas && item.status === "published" && <button className="table-action" onClick={() => onCorrect(item)}>Koreksi</button>}
            {!isPuskesmas && item.status === "published" && <button className="table-action" onClick={() => onView(item)}>Lihat rapor</button>}
          </td>
        </tr>
      )) : <TableMessage>Belum ada pemeriksaan pada periode ini.</TableMessage>}
    </tbody></table></div>
  );
}

function TableMessage({ children }: { children: string }) {
  return <tr><td className="table-message" colSpan={5}>{children}</td></tr>;
}

function ExaminationEditor({
  mode, record, student, user, semester, onCancel, onSaved,
}: {
  mode: "draft" | "correction";
  record?: Examination;
  student?: Student;
  user: User;
  semester?: Semester;
  onCancel: () => void;
  onSaved: () => Promise<void>;
}) {
  const [observations, setObservations] = useState<Observations>(() => normalizeObservations(record?.observations));
  const [examinedAt, setExaminedAt] = useState(record?.examined_at?.slice(0, 10) ?? today());
  const [examinerName, setExaminerName] = useState(record?.examiner_name ?? user.name);
  const [reason, setReason] = useState("");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const groups = [...new Set(indicators.map((item) => item.group))];
  const recordedCount = Object.values(observations).filter((item) => item.status === "recorded").length;

  function update(key: string, value: Observation) {
    setObservations((current) => ({ ...current, [key]: value }));
  }

  function serialize() {
    return Object.fromEntries(Object.entries(observations).map(([key, item]) => {
      if (item.status === "not_examined") return [key, { status: item.status }];
      const definition = indicators.find((indicator) => indicator.key === key);
      let value: string | number | boolean | null = item.value;
      if (definition?.type === "boolean") value = item.value === "true";
      else if (definition?.type === "number") value = item.value === "" ? null : Number(item.value);
      return [key, { status: item.status, value }];
    }));
  }

  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSaving(true);
    setError("");
    try {
      if (mode === "correction" && record) {
        await apiFetch(`examinations/${record.id}/corrections`, {
          method: "POST",
          body: JSON.stringify({
            reason,
            expected_version: record.version,
            observations: serialize(),
          }),
        });
      } else {
        const payload = { examined_at: examinedAt, examiner_name: examinerName, observations: serialize() };
        if (record) {
          await apiFetch(`examinations/${record.id}`, { method: "PATCH", body: JSON.stringify(payload) });
        } else if (student && semester) {
          await apiFetch("examinations", {
            method: "POST",
            body: JSON.stringify({
              student_id: student.id,
              semester_id: semester.id,
              ...payload,
            }),
          });
        } else {
          throw new Error("Periode atau siswa belum dipilih.");
        }
      }
      await onSaved();
    } catch (reason) {
      setError(formatApiError(reason));
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onCancel(); }}>
      <section className="editor-modal" role="dialog" aria-modal="true" aria-labelledby="editor-title">
        <header className="modal-header"><div><div className="eyebrow">{mode === "correction" ? "KOREKSI BERVERSI" : "FORMULIR PEMERIKSAAN"}</div><h2 id="editor-title">{mode === "correction" ? "Koreksi hasil" : record ? "Ubah draf pemeriksaan" : "Pemeriksaan baru"}</h2><p>{record?.student.name ?? student?.name}{record && ` · Versi ${record.version}`}</p></div><button className="icon-button" onClick={onCancel} aria-label="Tutup">×</button></header>
        <form onSubmit={(event) => void save(event)}>
          <div className="editor-content">
            {error && <div className="alert" role="alert"><span>!</span><p>{error}</p></div>}
            {mode === "draft" && <div className="form-row meta-fields">
              <label>Tanggal pemeriksaan<input type="date" value={examinedAt} min={semester?.starts_on} max={semester && semester.ends_on < today() ? semester.ends_on : today()} required onChange={(event) => setExaminedAt(event.target.value)} /></label>
              <label>Nama petugas pemeriksa<input value={examinerName} maxLength={255} required onChange={(event) => setExaminerName(event.target.value)} /></label>
            </div>}
            <div className="completion-note"><span className="completion-icon">✓</span><span><strong>{recordedCount} dari {indicators.length} indikator dicatat</strong><small>Pilih “Belum diperiksa” secara sadar bila indikator tidak dilakukan.</small></span></div>
            {groups.map((group) => (
              <section className="indicator-group" key={group}>
                <h3>{group}</h3>
                {indicators.filter((item) => item.group === group).map((definition) => {
                  const observation = observations[definition.key];
                  const recorded = observation.status === "recorded";
                  return <div className="indicator-row" key={definition.key}>
                    <div className="indicator-name"><strong>{definition.label}</strong>{definition.unit && <small>Satuan: {definition.unit}</small>}</div>
                    <div className="indicator-controls">
                      <select aria-label={`Status ${definition.label}`} value={observation.status} onChange={(event) => update(definition.key, { status: event.target.value as Observation["status"], value: event.target.value === "recorded" ? "" : null })}>
                        <option value="not_examined">Belum diperiksa</option><option value="recorded">Sudah diperiksa</option>
                      </select>
                      {definition.type === "boolean" || definition.type === "select" ? (
                        <select aria-label={definition.label} value={observation.value == null ? "" : String(observation.value)} disabled={!recorded} required={recorded} onChange={(event) => update(definition.key, { ...observation, value: event.target.value })}>
                          <option value="">Pilih hasil</option>{definition.choices?.map((choice) => <option key={choice.value} value={choice.value}>{choice.label}</option>)}
                        </select>
                      ) : (
                        <input aria-label={definition.label} type={definition.type === "number" ? "number" : "text"} min={definition.type === "number" ? "0.01" : undefined} max={definition.type === "number" ? "999.99" : undefined} step={definition.type === "number" ? "0.01" : undefined} maxLength={definition.type === "text" ? (definition.key === "blood_pressure" ? 32 : 2000) : undefined} placeholder={definition.type === "number" ? `Nilai ${definition.unit}` : "Isi hasil pemeriksaan"} value={observation.value == null ? "" : String(observation.value)} disabled={!recorded} required={recorded} onChange={(event) => update(definition.key, { ...observation, value: event.target.value })} />
                      )}
                    </div>
                  </div>;
                })}
              </section>
            ))}
            {mode === "correction" && <label className="reason-field">Alasan koreksi<textarea value={reason} onChange={(event) => setReason(event.target.value)} minLength={10} maxLength={1000} required placeholder="Jelaskan dasar dan perubahan koreksi (min. 10 karakter)" /></label>}
          </div>
          <footer className="modal-footer"><span>{mode === "correction" ? "Koreksi akan membuat versi baru dan hasil perlu dipublikasikan ulang." : "Draf dapat dilengkapi dan ditinjau sebelum finalisasi."}</span><div><button type="button" className="button button-quiet" onClick={onCancel}>Batal</button><button className="button button-primary" disabled={saving}>{saving ? "Menyimpan..." : mode === "correction" ? "Simpan koreksi" : "Simpan draf"}</button></div></footer>
        </form>
      </section>
    </div>
  );
}

function ReportModal({ examination, onClose }: { examination: Examination; onClose: () => void }) {
  const observations = normalizeObservations(examination.observations);
  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
      <section className="report-modal" role="dialog" aria-modal="true" aria-labelledby="report-title">
        <header className="modal-header"><div><div className="eyebrow">RAPOR KESEHATAN SISWA</div><h2 id="report-title">{examination.student.name}</h2><p>{examination.school.name} · {examination.examined_at} · {examination.examiner_name}</p></div><button className="icon-button" onClick={onClose} aria-label="Tutup">×</button></header>
        <div className="report-body">
          <div className="report-summary"><div><span>Status</span><strong>Terpublikasi · Versi {examination.version}</strong></div><div><span>IMT</span><strong>{examination.bmi ?? "Belum dihitung"}</strong></div></div>
          {indicators.map((indicator) => {
            const observation = observations[indicator.key];
            const value = observation.status === "not_examined"
              ? "Belum diperiksa"
              : indicator.choices?.find((choice) => choice.value === String(observation.value))?.label
                ?? (typeof observation.value === "boolean" ? (observation.value ? "Ya" : "Tidak") : String(observation.value ?? "—"));
            return <div className="report-row" key={indicator.key}><span>{indicator.label}</span><strong>{value}{indicator.unit && observation.status === "recorded" ? ` ${indicator.unit}` : ""}</strong></div>;
          })}
        </div>
        <footer className="modal-footer"><span>Rapor hanya menampilkan data yang telah dipublikasikan.</span><button className="button button-primary" onClick={onClose}>Tutup</button></footer>
      </section>
    </div>
  );
}
