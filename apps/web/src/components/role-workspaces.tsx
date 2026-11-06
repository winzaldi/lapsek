"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import type { ReactNode } from "react";
import Link from "next/link";
import { apiFetch, formatApiError, rowsFrom } from "@/lib/api";
import { indicators } from "@/lib/indicators";

type RoleUser = { id: number; name: string; email: string; roles: { name: string; label: string }[] };
type School = {
  id: number;
  health_center_id: number;
  name: string;
  level: string;
  npsn?: string | null;
  address?: string | null;
  is_active?: boolean;
  health_center?: { id: number; name: string };
};
type AcademicYear = { id: number; school_id: number; name: string; starts_on: string; ends_on: string; is_active: boolean };
type Semester = { id: number; academic_year_id: number; number: number; starts_on: string; ends_on: string; academic_year?: AcademicYear };
type SchoolClass = { id: number; school_id: number; name: string; grade: number; academic_level: string; is_active: boolean };
type Enrollment = {
  id: number;
  student_id: number;
  school_id: number;
  school_class_id: number | null;
  academic_year_id: number;
  starts_on: string;
  ends_on: string | null;
  student: Student;
  school_class: SchoolClass | null;
  academic_year: AcademicYear;
};
type Student = {
  id: number;
  name: string;
  nisn?: string | null;
  birth_date?: string | null;
  sex?: string | null;
  enrollments?: Enrollment[];
};
type Examination = {
  id: number;
  student: Student;
  school: { id: number; name: string };
  school_class: { id: number; name: string } | null;
  semester: Semester & { academic_year?: AcademicYear };
  examined_at: string;
  examiner_name: string;
  status: "published";
  version: number;
  bmi?: string | null;
  observations: Record<string, { status: string; value: unknown }>;
};
type HealthRecord = {
  id: number;
  school: { id: number; name: string };
  school_class: { id: number; name: string } | null;
  semester: Semester & { academic_year?: AcademicYear };
  examined_at: string;
  examiner_name: string;
  status: string;
  version: number;
  bmi?: string | null;
  observations: Record<string, { status: string; value: unknown }>;
};
type ReportGroup = {
  id: number | null;
  name: string;
  school_id?: number;
  school_name?: string;
  total: number;
  checked: number;
  not_checked: number;
  coverage_percent: number;
};
type ReportSummary = {
  period: { academic_year: string; semester: number | null };
  summary: { total: number; checked: number; not_checked: number; coverage_percent: number };
  by_school: ReportGroup[];
  by_class: ReportGroup[];
  privacy: { minimum_group_size: number; suppressed_school_groups: number; suppressed_class_groups: number };
};
type ReportExportRecord = {
  id: string;
  format: "csv" | "xlsx" | "pdf";
  status: "queued" | "processing" | "completed" | "failed" | "expired";
  filters: { academic_year: string; semester?: number; school_id?: number };
  row_count: number | null;
  failure_message: string | null;
  snapshot_at: string | null;
  created_at: string;
  expires_at: string;
  can_download: boolean;
};
type Session = { user: RoleUser; student_id?: number | null };
type SchoolTab = "overview" | "profile" | "students" | "enrollments" | "examinations";

const levelNames: Record<string, string> = { sd: "SD/Sederajat", smp: "SMP/Sederajat", sma: "SMA/Sederajat", slb: "SLB" };
const roleName = "Operator Sekolah / Guru";

function today() {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
}

function notice(error: unknown) {
  return formatApiError(error);
}

function printReport(mode: "summary" | "detail") {
  const className = mode === "summary" ? "laos-print-summary" : "laos-print-detail";
  document.body.classList.add(className);
  window.print();
  window.setTimeout(() => document.body.classList.remove(className), 1000);
}

function semesterName(semester: Semester) {
  return `${semester.academic_year?.name ?? "Tahun ajaran"} · Semester ${semester.number}`;
}

export function StudentWorkspace({
  session,
  onLogout,
}: {
  session: Session;
  onLogout: () => void;
}) {
  const studentId = session.student_id;
  const [records, setRecords] = useState<HealthRecord[]>([]);
  const [loading, setLoading] = useState(Boolean(studentId));
  const [message, setMessage] = useState("");
  const [selected, setSelected] = useState<HealthRecord | null>(null);

  useEffect(() => {
    if (!studentId) return;
    let active = true;
    apiFetch<unknown>(`students/${studentId}/health-history`)
      .then((response) => { if (active) setRecords(rowsFrom<HealthRecord>(response)); })
      .catch((error: unknown) => { if (active) setMessage(notice(error)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [studentId]);

  return (
    <RoleShell
      title={session.user.name}
      role="Siswa"
      active="Riwayat kesehatan"
      items={[["history", "♡", "Riwayat kesehatan"]]}
      onLogout={onLogout}
    >
      <section className="page-heading">
        <div><div className="eyebrow">RUANG SISWA · DATA PRIBADI</div><h1>Rapor kesehatan saya</h1><p>Riwayat ini hanya menampilkan pemeriksaan yang telah dipublikasikan untuk profil siswa tertaut.</p></div>
        <span className="status-pill status-done"><i />Privat</span>
      </section>
      {message && <InlineAlert message={message} onClose={() => setMessage("")} />}
      {!studentId ? (
        <section className="empty-state card"><div className="empty-icon">♡</div><h2>Akun belum ditautkan ke profil siswa</h2><p>Hubungi Operator Sekolah untuk memeriksa penautan akun. Demi keamanan, nama akun saja tidak digunakan untuk mencari data siswa.</p></section>
      ) : (
        <>
          <section className="student-welcome-card card">
            <div className="student-welcome-mark">♡</div>
            <div><span>PROFIL SISWA</span><h2>{session.user.name}</h2><p>Data kesehatan hanya dapat dilihat setelah Puskesmas memublikasikan hasil pemeriksaan.</p></div>
            <strong>{records.length}<small>hasil tersedia</small></strong>
          </section>
          <div className="section-heading student-history-heading"><div><h2>Riwayat pemeriksaan</h2><p>Hasil dari semester yang telah dipublikasikan.</p></div></div>
          <section className="card student-history-list">
            {loading ? <div className="loading-inline"><span className="spinner" />Memuat riwayat kesehatan...</div> : records.length ? records.map((record) => (
              <article className="history-card" key={record.id}>
                <div className="history-date"><strong>{new Date(`${record.examined_at}T12:00:00`).toLocaleDateString("id-ID", { day: "2-digit", month: "short" })}</strong><span>{new Date(`${record.examined_at}T12:00:00`).getFullYear()}</span></div>
                <div className="history-main"><span className="eyebrow">{semesterName(record.semester)}</span><h3>{record.school.name}</h3><p>{record.school_class?.name ?? "Kelas tidak tersedia"} · Pemeriksa: {record.examiner_name}</p></div>
                <div className="history-meta"><span className="status-pill status-done"><i />Terpublikasi</span><button className="button button-outline button-small" onClick={() => setSelected(record)}>Lihat hasil <span>→</span></button></div>
              </article>
            )) : (
              <div className="student-empty"><div className="empty-icon">◷</div><h3>Belum ada hasil yang tersedia</h3><p>Hasil pemeriksaan akan muncul di sini setelah Puskesmas menyelesaikan dan memublikasikannya.</p></div>
            )}
          </section>
          <div className="student-privacy-hint"><span>◈</span>Jangan membagikan tangkapan layar rapor kesehatan kepada orang yang tidak berwenang.</div>
        </>
      )}
      {selected && <HealthRecordModal record={selected} onClose={() => setSelected(null)} />}
    </RoleShell>
  );
}

export function SchoolWorkspace({
  user,
  onLogout,
}: {
  user: RoleUser;
  onLogout: () => void;
}) {
  const [schools, setSchools] = useState<School[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [semesters, setSemesters] = useState<Semester[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
  const [examinations, setExaminations] = useState<Examination[]>([]);
  const [examinationLoading, setExaminationLoading] = useState(true);
  const [examinationRefreshKey, setExaminationRefreshKey] = useState(0);
  const [schoolId, setSchoolId] = useState("");
  const [semesterId, setSemesterId] = useState("");
  const [activeTab, setActiveTab] = useState<SchoolTab | "reports">("overview");
  const [selectedStudentId, setSelectedStudentId] = useState("");
  const [selectedClassId, setSelectedClassId] = useState("");
  const [enrollmentAction, setEnrollmentAction] = useState<{ enrollment: Enrollment; action: "close" | "transfer" } | null>(null);
  const [selectedRecord, setSelectedRecord] = useState<Examination | null>(null);
  const [search, setSearch] = useState("");
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const school = schools.find((item) => String(item.id) === schoolId);
  const schoolYears = useMemo(() => years.filter((year) => String(year.school_id) === schoolId), [years, schoolId]);
  const schoolSemesters = useMemo(() => semesters.filter((semester) => schoolYears.some((year) => year.id === semester.academic_year_id)), [semesters, schoolYears]);
  const selectedSemester = schoolSemesters.find((semester) => String(semester.id) === semesterId);
  const activeEnrollments = enrollments.filter((enrollment) => enrollment.ends_on === null);
  const filteredStudents = students.filter((student) => {
    const query = search.trim().toLocaleLowerCase("id");
    return !query || student.name.toLocaleLowerCase("id").includes(query) || (student.nisn ?? "").includes(query);
  });
  const filteredExaminations = examinations.filter((record) => {
    const query = search.trim().toLocaleLowerCase("id");
    return !query || record.student.name.toLocaleLowerCase("id").includes(query) || (record.student.nisn ?? "").includes(query);
  });

  const loadSchoolData = useCallback(async (selectedSchoolId: string, selectedSemesterId: string) => {
    if (!selectedSchoolId) return;
    setLoading(true);
    setMessage("");
    try {
      const [studentResult, classResult, enrollmentResult, yearResult, semesterResult] = await Promise.all([
        apiFetch<unknown>(`students?school_id=${selectedSchoolId}`),
        apiFetch<unknown>(`classes?school_id=${selectedSchoolId}`),
        apiFetch<unknown>(`enrollments?school_id=${selectedSchoolId}`),
        apiFetch<unknown>("academic-years"),
        apiFetch<unknown>("semesters"),
      ]);
      const nextStudents = rowsFrom<Student>(studentResult);
      const nextYears = rowsFrom<AcademicYear>(yearResult);
      const nextSemesters = rowsFrom<Semester>(semesterResult);
      setStudents(nextStudents);
      setClasses(rowsFrom<SchoolClass>(classResult));
      setEnrollments(rowsFrom<Enrollment>(enrollmentResult));
      setYears(nextYears);
      setSemesters(nextSemesters);
      const schoolYearIds = nextYears.filter((year) => String(year.school_id) === selectedSchoolId).map((year) => year.id);
      const nextSchoolSemesters = nextSemesters.filter((item) => schoolYearIds.includes(item.academic_year_id));
      if (!nextSchoolSemesters.some((item) => String(item.id) === selectedSemesterId)) {
        const latest = [...nextSchoolSemesters].sort((a, b) => b.starts_on.localeCompare(a.starts_on))[0];
        setSemesterId(latest ? String(latest.id) : "");
      }
    } catch (error) {
      setMessage(notice(error));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;
    Promise.all([apiFetch<unknown>("schools"), apiFetch<unknown>("academic-years"), apiFetch<unknown>("semesters")])
      .then(([schoolResult, yearResult, semesterResult]) => {
        if (!active) return;
        const nextSchools = rowsFrom<School>(schoolResult);
        const nextYears = rowsFrom<AcademicYear>(yearResult);
        const nextSemesters = rowsFrom<Semester>(semesterResult);
        setSchools(nextSchools);
        setYears(nextYears);
        setSemesters(nextSemesters);
        const firstSchool = nextSchools[0];
        if (!firstSchool) {
          setLoading(false);
          return;
        }
        const yearIds = nextYears.filter((year) => year.school_id === firstSchool.id).map((year) => year.id);
        const latest = nextSemesters.filter((semester) => yearIds.includes(semester.academic_year_id)).sort((a, b) => b.starts_on.localeCompare(a.starts_on))[0];
        setSchoolId(String(firstSchool.id));
        setSemesterId(latest ? String(latest.id) : "");
        setExaminationLoading(Boolean(latest));
        void loadSchoolData(String(firstSchool.id), latest ? String(latest.id) : "");
      })
      .catch((error: unknown) => { if (active) { setMessage(notice(error)); setLoading(false); } });
    return () => { active = false; };
  }, [loadSchoolData]);

  useEffect(() => {
    if (!schoolId || !semesterId) return;
    let active = true;
    apiFetch<unknown>(`examinations?school_id=${schoolId}&semester_id=${semesterId}`)
      .then((response) => { if (active) setExaminations(rowsFrom<Examination>(response)); })
      .catch((error: unknown) => { if (active) setMessage(notice(error)); })
      .finally(() => { if (active) setExaminationLoading(false); });
    return () => { active = false; };
  }, [schoolId, semesterId, examinationRefreshKey]);

  async function saveStudent(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const selected = students.find((student) => String(student.id) === selectedStudentId);
    const payload = {
      name: form.get("name"),
      nisn: form.get("nisn") || null,
      birth_date: form.get("birth_date") || null,
      sex: form.get("sex") || null,
    };
    setBusy(true);
    setMessage("");
    try {
      if (selected) {
        await apiFetch(`students/${selected.id}`, { method: "PATCH", body: JSON.stringify(payload) });
        setMessage("Profil siswa berhasil diperbarui.");
      } else {
        await apiFetch("students", {
          method: "POST",
          body: JSON.stringify({
            ...payload,
            school_id: Number(schoolId),
            academic_year_id: Number(form.get("academic_year_id")),
            school_class_id: form.get("school_class_id") ? Number(form.get("school_class_id")) : null,
            starts_on: form.get("starts_on"),
          }),
        });
        setMessage("Siswa dan enrollment berhasil dibuat.");
      }
      setSelectedStudentId("");
      setBusy(false);
      await loadSchoolData(schoolId, semesterId);
    } catch (error) {
      setMessage(notice(error));
      setBusy(false);
    }
  }

  async function saveClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const selected = classes.find((item) => String(item.id) === selectedClassId);
    const payload = { name: form.get("name"), grade: Number(form.get("grade")), academic_level: school?.level };
    setBusy(true);
    setMessage("");
    try {
      await apiFetch(selected ? `classes/${selected.id}` : "classes", {
        method: selected ? "PATCH" : "POST",
        body: JSON.stringify(selected ? payload : { ...payload, school_id: Number(schoolId) }),
      });
      setMessage(selected ? "Data kelas berhasil diperbarui." : "Kelas berhasil ditambahkan.");
      setSelectedClassId("");
      setBusy(false);
      await loadSchoolData(schoolId, semesterId);
    } catch (error) {
      setMessage(notice(error));
      setBusy(false);
    }
  }

  async function saveEnrollment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const enrollment = enrollmentAction?.enrollment;
    if (!enrollment) return;
    setBusy(true);
    setMessage("");
    try {
      if (enrollmentAction.action === "close") {
        await apiFetch(`enrollments/${enrollment.id}/close`, {
          method: "PATCH",
          body: JSON.stringify({ ends_on: form.get("ends_on") }),
        });
        setMessage("Enrollment berhasil ditutup.");
      } else {
        await apiFetch(`enrollments/${enrollment.id}/transfer`, {
          method: "POST",
          body: JSON.stringify({
            school_id: Number(form.get("school_id")),
            school_class_id: form.get("school_class_id") ? Number(form.get("school_class_id")) : null,
            academic_year_id: Number(form.get("academic_year_id")),
            starts_on: form.get("starts_on"),
          }),
        });
        setMessage("Perpindahan siswa berhasil dicatat.");
      }
      setEnrollmentAction(null);
      setBusy(false);
      await loadSchoolData(schoolId, semesterId);
    } catch (error) {
      setMessage(notice(error));
      setBusy(false);
    }
  }

  async function toggleClass(item: SchoolClass) {
    const action = item.is_active ? "menonaktifkan" : "mengaktifkan";
    if (!window.confirm(`Apakah Anda yakin ingin ${action} kelas ${item.name}?`)) return;
    setBusy(true);
    setMessage("");
    try {
      await apiFetch(`classes/${item.id}`, {
        method: "PATCH",
        body: JSON.stringify({ is_active: !item.is_active }),
      });
      setMessage(`Kelas ${item.name} berhasil ${item.is_active ? "dinonaktifkan" : "diaktifkan"}.`);
      await loadSchoolData(schoolId, semesterId);
    } catch (error) {
      setMessage(notice(error));
    } finally {
      setBusy(false);
    }
  }

  const navItems = [
    ["overview", "◫", "Ringkasan"],
    ["profile", "⌂", "Profil sekolah"],
    ["students", "♧", "Siswa & kelas"],
    ["enrollments", "⇄", "Enrollment"],
    ["examinations", "♡", "Pemeriksaan"],
    ["reports", "▤", "Laporan"],
  ] as const;

  return (
    <RoleShell
      title={user.name}
      role={roleName}
      active={navItems.find(([key]) => key === activeTab)?.[2] ?? "Ringkasan"}
      items={navItems}
      onNavigate={(key) => { setActiveTab(key as SchoolTab); setSearch(""); }}
      onLogout={onLogout}
    >
      <section className="page-heading">
        <div><div className="eyebrow">SEKOLAH · ADMINISTRASI UKS</div><h1>{navItems.find(([key]) => key === activeTab)?.[2]}</h1><p>{school?.name ?? "Data sekolah sesuai penugasan akun."}</p></div>
        <button className="button button-quiet" onClick={() => { void loadSchoolData(schoolId, semesterId); setExaminationLoading(Boolean(semesterId)); setExaminationRefreshKey((current) => current + 1); }} disabled={loading || !schoolId}><span>↻</span> Perbarui data</button>
      </section>
      {message && <InlineAlert message={message} onClose={() => setMessage("")} />}
      {!school ? (
        <section className="empty-state card"><div className="empty-icon">⌂</div><h2>Sekolah belum ditugaskan</h2><p>Hubungi Admin Sistem untuk mengatur cakupan sekolah akun Anda.</p></section>
      ) : (
        <>
          {activeTab === "reports" ? (
            <ReportsPanel roleName="school_operator" initialSchoolId={schoolId} />
          ) : <>
          <section className="role-filters card">
            <label>Sekolah<select value={schoolId} onChange={(event) => {
              const nextId = event.target.value;
              setSchoolId(nextId);
              const nextYearIds = years.filter((year) => String(year.school_id) === nextId).map((year) => year.id);
              const latest = semesters.filter((semester) => nextYearIds.includes(semester.academic_year_id)).sort((a, b) => b.starts_on.localeCompare(a.starts_on))[0];
              setSemesterId(latest ? String(latest.id) : "");
              setStudents([]);
              setClasses([]);
              setEnrollments([]);
              setExaminations([]);
              setExaminationLoading(Boolean(latest));
              void loadSchoolData(nextId, latest ? String(latest.id) : "");
            }} disabled={schools.length < 2}>{schools.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
            <label>Periode pemeriksaan<select value={semesterId} onChange={(event) => { setSemesterId(event.target.value); setExaminations([]); setExaminationLoading(Boolean(event.target.value)); }} disabled={!schoolSemesters.length}><option value="">Pilih semester</option>{schoolSemesters.map((semester) => <option key={semester.id} value={semester.id}>{semesterName({ ...semester, academic_year: years.find((year) => year.id === semester.academic_year_id) })}</option>)}</select></label>
            <label className="role-search">Cari siswa<span className="search-control"><span>⌕</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau NISN" /></span></label>
          </section>

          {activeTab === "overview" && (
            <>
              <section className="stat-grid">
                <RoleStat label="Siswa aktif" value={activeEnrollments.length} note="Enrollment aktif" icon="♧" />
                <RoleStat label="Kelas" value={classes.filter((item) => item.is_active).length} note="Kelas aktif di sekolah" icon="▦" />
                <RoleStat label="Hasil tersedia" value={examinations.length} note={selectedSemester ? semesterName({ ...selectedSemester, academic_year: years.find((year) => year.id === selectedSemester.academic_year_id) }) : "Pilih semester"} icon="♡" />
                <RoleStat label="Status sekolah" value={school.is_active === false ? "Nonaktif" : "Aktif"} note={school.health_center?.name ?? "Puskesmas pembina"} icon="⌂" />
              </section>
              <section className="school-profile-summary card">
                <div><span className="eyebrow">PROFIL SEKOLAH</span><h2>{school.name}</h2><p>{levelNames[school.level] ?? school.level} · {school.npsn ? `NPSN ${school.npsn}` : "NPSN belum diisi"}</p></div>
                <button className="button button-outline button-small" onClick={() => setActiveTab("profile")}>Lihat profil →</button>
              </section>
              <div className="section-heading"><div><h2>Hasil pemeriksaan terbaru</h2><p>Hasil klinis hanya dapat dilihat setelah dipublikasikan Puskesmas.</p></div><button className="button button-quiet" onClick={() => setActiveTab("examinations")}>Lihat semua →</button></div>
              <SchoolExaminationTable items={filteredExaminations.slice(0, 6)} loading={loading || examinationLoading} onView={setSelectedRecord} />
            </>
          )}

          {activeTab === "profile" && (
            <section className="card profile-card">
              <div className="profile-card-heading"><span className="title-icon">⌂</span><div><h2>Profil sekolah</h2><p>Informasi master dikelola oleh Admin Sistem.</p></div></div>
              <div className="profile-fields">
                <ProfileValue label="Nama sekolah" value={school.name} />
                <ProfileValue label="Jenjang" value={levelNames[school.level] ?? school.level} />
                <ProfileValue label="NPSN" value={school.npsn ?? "Belum diisi"} />
                <ProfileValue label="Puskesmas pembina" value={school.health_center?.name ?? "Belum ditentukan"} />
                <ProfileValue label="Alamat" value={school.address ?? "Belum diisi"} />
                <ProfileValue label="Status" value={school.is_active === false ? "Nonaktif" : "Aktif"} />
              </div>
              <p className="form-hint">Untuk mengubah nama, jenjang, alamat, NPSN, atau Puskesmas pembina, hubungi Admin Sistem. Perubahan profil sekolah tidak mengubah snapshot arsip pemeriksaan yang sudah tersimpan.</p>
            </section>
          )}

          {activeTab === "students" && (
            <section className="school-management">
              <div className="school-management-grid">
                <section className="card form-card">
                  <div className="card-title"><span className="title-icon">♧</span><div><h2>{selectedStudentId ? "Ubah profil siswa" : "Tambah siswa"}</h2><p>{selectedStudentId ? "Perbarui data demografis, tanpa mengubah arsip pemeriksaan." : "Siswa baru sekaligus mendapat enrollment sekolah."}</p></div></div>
                  <form className="stack-form" onSubmit={(event) => void saveStudent(event)}>
                    <label>Pilih siswa untuk mengubah data<select value={selectedStudentId} onChange={(event) => setSelectedStudentId(event.target.value)}><option value="">Tambah siswa baru</option>{students.map((item) => <option key={item.id} value={item.id}>{item.name}{item.nisn ? ` · ${item.nisn}` : ""}</option>)}</select></label>
                    <label>Nama lengkap<input key={`student-name-${selectedStudentId}`} name="name" required maxLength={255} defaultValue={students.find((item) => String(item.id) === selectedStudentId)?.name ?? ""} /></label>
                    <div className="form-row"><label>NISN<input key={`student-nisn-${selectedStudentId}`} name="nisn" maxLength={20} defaultValue={students.find((item) => String(item.id) === selectedStudentId)?.nisn ?? ""} /></label><label>Tanggal lahir<input key={`student-birth-${selectedStudentId}`} name="birth_date" type="date" max={today()} defaultValue={students.find((item) => String(item.id) === selectedStudentId)?.birth_date ?? ""} /></label></div>
                    <label>Jenis kelamin<select key={`student-sex-${selectedStudentId}`} name="sex" defaultValue={students.find((item) => String(item.id) === selectedStudentId)?.sex ?? ""}><option value="">Pilih</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select></label>
                    {!selectedStudentId && <>
                      <label>Tahun ajaran<select name="academic_year_id" required defaultValue=""><option value="">Pilih tahun ajaran</option>{schoolYears.map((year) => <option key={year.id} value={year.id}>{year.name}{year.is_active ? " · Aktif" : ""}</option>)}</select></label>
                      <label>Kelas<select name="school_class_id" defaultValue=""><option value="">Belum ditentukan</option>{classes.filter((item) => item.is_active).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                      <label>Tanggal mulai terdaftar<input name="starts_on" type="date" required defaultValue={today()} /></label>
                    </>}
                    <div className="form-actions"><button className="button button-primary" disabled={busy || (!selectedStudentId && !schoolYears.length)}>{busy ? "Menyimpan..." : selectedStudentId ? "Simpan perubahan" : "Tambah siswa"}</button>{selectedStudentId && <button type="button" className="button button-quiet" onClick={() => setSelectedStudentId("")}>Batal ubah</button>}</div>
                  </form>
                </section>
                <section className="card form-card">
                  <div className="card-title"><span className="title-icon violet">▦</span><div><h2>{selectedClassId ? "Ubah kelas" : "Tambah kelas"}</h2><p>Kelas mengikuti jenjang {levelNames[school.level] ?? school.level}.</p></div></div>
                  <form className="stack-form" onSubmit={(event) => void saveClass(event)}>
                    <label>Pilih kelas untuk diubah<select value={selectedClassId} onChange={(event) => setSelectedClassId(event.target.value)}><option value="">Tambah kelas baru</option>{classes.map((item) => <option key={item.id} value={item.id}>{item.name}{item.is_active ? "" : " · Nonaktif"}</option>)}</select></label>
                    <label>Nama kelas<input key={`class-name-${selectedClassId}`} name="name" required maxLength={64} defaultValue={classes.find((item) => String(item.id) === selectedClassId)?.name ?? ""} placeholder="Contoh: 4-A" /></label>
                    <label>Tingkat kelas<input key={`class-grade-${selectedClassId}`} name="grade" type="number" min={1} max={12} required defaultValue={classes.find((item) => String(item.id) === selectedClassId)?.grade ?? ""} /></label>
                    <div className="form-actions"><button className="button button-outline" disabled={busy}>{selectedClassId ? "Simpan perubahan" : "Simpan kelas"}</button>{selectedClassId && <button type="button" className="button button-quiet" onClick={() => setSelectedClassId("")}>Batal ubah</button>}</div>
                  </form>
                  <div className="class-list"><strong>Kelas di sekolah</strong>{classes.length ? classes.map((item) => <span key={item.id}>{item.name}<small>{`Tingkat ${item.grade} · ${item.is_active ? "Aktif" : "Nonaktif"}`}</small><button className="table-action" disabled={busy} onClick={() => void toggleClass(item)}>{item.is_active ? "Nonaktifkan" : "Aktifkan"}</button></span>) : <p>Belum ada kelas.</p>}</div>
                </section>
              </div>
              <section className="card table-card"><div className="table-heading"><div><h2>Daftar siswa</h2><span>Gunakan tombol ubah untuk memperbarui profil siswa.</span></div><span className="record-count">{filteredStudents.length} siswa</span></div><div className="table-scroll"><table><thead><tr><th>Nama siswa</th><th>NISN</th><th>Kelas aktif</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                {loading ? <SchoolTableMessage>Memuat data...</SchoolTableMessage> : filteredStudents.length ? filteredStudents.map((item) => { const enrollment = item.enrollments?.[0]; return <tr key={item.id}><td><strong>{item.name}</strong><small className="table-subtext">{item.birth_date ?? "Tanggal lahir belum diisi"}</small></td><td>{item.nisn ?? "—"}</td><td>{enrollment?.school_class?.name ?? "—"}</td><td><span className={`status-pill ${enrollment ? "status-done" : "status-draft"}`}><i />{enrollment ? "Terdaftar" : "Tidak aktif"}</span></td><td><button className="table-action" onClick={() => setSelectedStudentId(String(item.id))}>Ubah profil</button></td></tr>; }) : <SchoolTableMessage>Belum ada siswa yang cocok dengan pencarian.</SchoolTableMessage>}
              </tbody></table></div></section>
            </section>
          )}

          {activeTab === "enrollments" && (
            <section className="card table-card">
              <div className="table-heading"><div><h2>Riwayat enrollment</h2><span>Penutupan dan perpindahan tersimpan sebagai histori; data pemeriksaan tidak dihapus.</span></div><span className="record-count">{enrollments.length} enrollment</span></div>
              <div className="table-scroll"><table><thead><tr><th>Siswa</th><th>Tahun ajaran</th><th>Kelas</th><th>Mulai</th><th>Selesai</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                {loading ? <SchoolTableMessage>Memuat enrollment...</SchoolTableMessage> : enrollments.length ? enrollments.map((item) => <tr key={item.id}><td><strong>{item.student?.name}</strong><small className="table-subtext">{item.student?.nisn ?? "NISN belum diisi"}</small></td><td>{item.academic_year?.name ?? "—"}</td><td>{item.school_class?.name ?? "—"}</td><td>{item.starts_on}</td><td>{item.ends_on ?? "—"}</td><td><span className={`status-pill ${item.ends_on ? "status-draft" : "status-done"}`}><i />{item.ends_on ? "Selesai" : "Aktif"}</span></td><td className="actions-cell">{!item.ends_on && <><button className="table-action" onClick={() => setEnrollmentAction({ enrollment: item, action: "close" })}>Akhiri</button><button className="table-action" onClick={() => setEnrollmentAction({ enrollment: item, action: "transfer" })}>Pindahkan</button></>}</td></tr>) : <SchoolTableMessage>Belum ada enrollment.</SchoolTableMessage>}
              </tbody></table></div>
            </section>
          )}

          {activeTab === "examinations" && (
            <>
              <section className="section-heading"><div><h2>Hasil pemeriksaan terpublikasi</h2><p>Sekolah hanya dapat membaca hasil, bukan mengubah data klinis.</p></div></section>
              <SchoolExaminationTable items={filteredExaminations} loading={loading || examinationLoading} onView={setSelectedRecord} />
            </>
          )}
          </>}
        </>
      )}
      {enrollmentAction && <EnrollmentModal action={enrollmentAction} schools={schools} years={years} classes={classes} busy={busy} onClose={() => setEnrollmentAction(null)} onSubmit={saveEnrollment} />}
      {selectedRecord && <SchoolReportModal record={selectedRecord} onClose={() => setSelectedRecord(null)} />}
    </RoleShell>
  );
}

export function ReportsPanel({
  roleName: userRole,
  initialSchoolId = "",
}: {
  roleName: string;
  initialSchoolId?: string;
}) {
  const [schools, setSchools] = useState<School[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [schoolId, setSchoolId] = useState(initialSchoolId);
  const [academicYear, setAcademicYear] = useState("");
  const [semester, setSemester] = useState("");
  const [schoolClassId, setSchoolClassId] = useState("");
  const [level, setLevel] = useState("");
  const [exportStatus, setExportStatus] = useState(userRole === "school_operator" ? "published" : "");
  const [exportFormat, setExportFormat] = useState<ReportExportRecord["format"]>("csv");
  const [summary, setSummary] = useState<ReportSummary | null>(null);
  const [exports, setExports] = useState<ReportExportRecord[]>([]);
  const [historyPage, setHistoryPage] = useState(1);
  const [historyLastPage, setHistoryLastPage] = useState(1);
  const [historyLoading, setHistoryLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [optionsLoading, setOptionsLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [message, setMessage] = useState("");
  const [refreshKey, setRefreshKey] = useState(0);

  const yearNames = useMemo(() => [...new Set(years
    .filter((year) => !schoolId || String(year.school_id) === schoolId)
    .map((year) => year.name))].sort((a, b) => b.localeCompare(a)), [schoolId, years]);
  const visibleClasses = classes.filter((item) =>
    (!schoolId || String(item.school_id) === schoolId)
    && (!level || item.academic_level === level),
  );
  const selectedSchool = schools.find((item) => String(item.id) === schoolId);
  const hasPendingExports = exports.some((item) => item.status === "queued" || item.status === "processing");

  useEffect(() => {
    let active = true;
    Promise.all([
      apiFetch<unknown>("schools"),
      apiFetch<unknown>("academic-years"),
      apiFetch<unknown>("classes"),
    ]).then(([schoolResponse, yearResponse, classResponse]) => {
      if (!active) return;
      const nextSchools = rowsFrom<School>(schoolResponse);
      const nextYears = rowsFrom<AcademicYear>(yearResponse);
      setSchools(nextSchools);
      setYears(nextYears);
      setClasses(rowsFrom<SchoolClass>(classResponse));
      setSchoolId((current) => {
        const preferred = current || initialSchoolId;
        if (preferred && nextSchools.some((item) => String(item.id) === preferred)) return preferred;
        return "";
      });
      const preferredYears = nextYears.filter((year) => !initialSchoolId || String(year.school_id) === initialSchoolId);
      setAcademicYear((current) => current || [...new Set(preferredYears.map((year) => year.name))].sort((a, b) => b.localeCompare(a))[0] || "");
    }).catch((error: unknown) => { if (active) setMessage(notice(error)); })
      .finally(() => { if (active) setOptionsLoading(false); });
    return () => { active = false; };
  }, [initialSchoolId]);

  useEffect(() => {
    if (optionsLoading || !academicYear) return;
    let active = true;
    const query = new URLSearchParams({ academic_year: academicYear });
    if (schoolId) query.set("school_id", schoolId);
    if (semester) query.set("semester", semester);
    if (schoolClassId) query.set("school_class_id", schoolClassId);
    if (level) query.set("level", level);
    apiFetch<ReportSummary>(`reports/summary?${query.toString()}`)
      .then((response) => { if (active) setSummary(response); })
      .catch((error: unknown) => { if (active) { setSummary(null); setMessage(notice(error)); } })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [academicYear, level, optionsLoading, refreshKey, schoolClassId, schoolId, semester]);

  useEffect(() => {
    let active = true;
    const loadHistory = () => {
      apiFetch<unknown>("reports/exports?page=1")
        .then((response) => {
          if (!active) return;
          const payload = response as { data?: { last_page?: number } };
          const latest = rowsFrom<ReportExportRecord>(response);
          setHistoryLastPage(payload.data?.last_page ?? 1);
          setExports((current) => [...latest, ...current.filter((item) => !latest.some((row) => row.id === item.id))]);
        })
        .catch((error: unknown) => { if (active) setMessage(notice(error)); });
    };
    loadHistory();
    const timer = hasPendingExports ? window.setInterval(loadHistory, 3000) : undefined;
    return () => {
      active = false;
      if (timer !== undefined) window.clearInterval(timer);
    };
  }, [hasPendingExports, refreshKey]);

  async function loadMoreExports() {
    if (historyLoading || historyPage >= historyLastPage) return;
    const nextPage = historyPage + 1;
    setHistoryLoading(true);
    try {
      const response = await apiFetch<unknown>(`reports/exports?page=${nextPage}`);
      const rows = rowsFrom<ReportExportRecord>(response);
      setExports((current) => [...current, ...rows.filter((row) => !current.some((item) => item.id === row.id))]);
      setHistoryPage(nextPage);
      const payload = response as { data?: { last_page?: number } };
      setHistoryLastPage(payload.data?.last_page ?? nextPage);
    } catch (error) {
      setMessage(notice(error));
    } finally {
      setHistoryLoading(false);
    }
  }

  async function createExport() {
    if (!academicYear) return;
    setExporting(true);
    setMessage("");
    const filters: Record<string, string> = { academic_year: academicYear, format: exportFormat };
    if (schoolId) filters.school_id = schoolId;
    if (semester) filters.semester = semester;
    if (schoolClassId) filters.school_class_id = schoolClassId;
    if (level) filters.level = level;
    if (exportStatus) filters.status = exportStatus;
    try {
      const response = await apiFetch<{ data: ReportExportRecord }>("reports/exports", {
        method: "POST",
        body: JSON.stringify(filters),
      });
      setExports((current) => [response.data, ...current.filter((item) => item.id !== response.data.id)]);
    } catch (error) {
      setMessage(notice(error));
    } finally {
      setExporting(false);
    }
  }

  return (
    <section className="reports-workspace">
      <div className="page-heading report-heading">
        <div><div className="eyebrow">LAPORAN · DATA TEROTORISASI</div><h1>Rekap pemeriksaan UKS</h1><p>Rekap hanya menghitung hasil yang dapat diakses akun Anda; draf tidak masuk dalam angka laporan.</p></div>
        <div className="report-heading-actions"><button className="button button-outline" onClick={() => printReport("summary")} disabled={!summary || loading}><span>▤</span> Cetak / Simpan PDF</button><button className="button button-quiet" onClick={() => { setLoading(true); setRefreshKey((current) => current + 1); }} disabled={loading || optionsLoading}><span>↻</span> Perbarui laporan</button></div>
      </div>
      {message && <InlineAlert message={message} onClose={() => setMessage("")} />}
      <section className="role-filters card report-filters">
        <label>Sekolah<select value={schoolId} onChange={(event) => {
          const nextSchoolId = event.target.value;
          const nextYearNames = [...new Set(years.filter((year) => !nextSchoolId || String(year.school_id) === nextSchoolId).map((year) => year.name))].sort((a, b) => b.localeCompare(a));
          setLoading(true);
          setSchoolId(nextSchoolId);
          setSchoolClassId("");
          setAcademicYear((current) => nextYearNames.includes(current) ? current : nextYearNames[0] ?? "");
        }} disabled={optionsLoading || Boolean(initialSchoolId)}><option value="">Semua sekolah dalam cakupan</option>{schools.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        <label>Tahun ajaran<select value={academicYear} onChange={(event) => { setLoading(true); setAcademicYear(event.target.value); }} disabled={optionsLoading || !yearNames.length}><option value="">Pilih tahun ajaran</option>{yearNames.map((name) => <option key={name} value={name}>{name}</option>)}</select></label>
        <label>Periode<select value={semester} onChange={(event) => { setLoading(true); setSemester(event.target.value); }}><option value="">Satu tahun ajaran</option><option value="1">Semester 1</option><option value="2">Semester 2</option></select></label>
        <label>Jenjang<select value={level} onChange={(event) => { setLoading(true); setLevel(event.target.value); setSchoolClassId(""); }}><option value="">Semua jenjang</option>{Object.entries(levelNames).map(([key, name]) => <option key={key} value={key}>{name}</option>)}</select></label>
        <label>Kelas<select value={schoolClassId} onChange={(event) => { setLoading(true); setSchoolClassId(event.target.value); }} disabled={!visibleClasses.length}><option value="">Semua kelas</option>{visibleClasses.map((item) => <option key={item.id} value={item.id}>{selectedSchool ? item.name : `${schools.find((school) => school.id === item.school_id)?.name ?? "Sekolah"} · ${item.name}`}</option>)}</select></label>
      </section>
      <section className="report-results" aria-live="polite">
        {optionsLoading || (loading && Boolean(academicYear)) ? <div className="card loading-inline"><span className="spinner" />Menghitung rekap...</div> : summary ? <>
          <section className="stat-grid report-stat-grid">
            <RoleStat label="Sasaran terdaftar" value={summary.summary.total} note={summary.period.semester ? `Semester ${summary.period.semester} · ${summary.period.academic_year}` : `Tahun ajaran ${summary.period.academic_year}`} icon="♧" />
            <RoleStat label="Sudah diperiksa" value={summary.summary.checked} note="Sesuai akses akun Anda" icon="✓" />
            <RoleStat label="Belum diperiksa" value={summary.summary.not_checked} note="Belum ada hasil yang dapat dihitung" icon="◷" />
            <RoleStat label="Cakupan" value={`${summary.summary.coverage_percent}%`} note="Dari seluruh sasaran terdaftar" icon="↗" />
          </section>
          <section className="card report-chart-card">
            <div className="report-section-heading"><div><h2>Cakupan per sekolah</h2><p>Hanya kelompok dengan minimal {summary.privacy.minimum_group_size} sasaran ditampilkan.</p></div></div>
            <CoverageChart groups={summary.by_school} />
            {!summary.by_school.length && <div className="report-chart-empty">Belum ada kelompok sekolah yang memenuhi batas minimum tampilan.</div>}
            {summary.privacy.suppressed_school_groups > 0 && <p className="suppression-note">{summary.privacy.suppressed_school_groups} kelompok sekolah disembunyikan untuk melindungi kelompok kecil.</p>}
          </section>
          <section className="card report-chart-card">
            <div className="report-section-heading"><div><h2>Cakupan per kelas</h2><p>Angka menampilkan jumlah sasaran dan pemeriksaan final/publikasi.</p></div></div>
            <CoverageChart groups={summary.by_class.map((item) => ({ ...item, name: `${item.school_name ?? ""} · ${item.name}` }))} />
            {!summary.by_class.length && <div className="report-chart-empty">Belum ada kelompok kelas yang memenuhi batas minimum tampilan.</div>}
            {summary.privacy.suppressed_class_groups > 0 && <p className="suppression-note">{summary.privacy.suppressed_class_groups} kelompok kelas disembunyikan untuk melindungi kelompok kecil.</p>}
          </section>
          <section className="card report-export-card">
            <div><span className="title-icon violet">↓</span><div><h2>Ekspor data pemeriksaan</h2><p>File diproses melalui antrean, disimpan privat selama 7 hari, dan tersedia kembali di riwayat ekspor.</p></div></div>
            <label>Status hasil<select value={exportStatus} onChange={(event) => setExportStatus(event.target.value)} disabled={userRole === "school_operator"}><option value="">Semua hasil final</option><option value="finalized">Final</option><option value="published">Terpublikasi</option></select></label>
            <label>Format<select value={exportFormat} onChange={(event) => setExportFormat(event.target.value as ReportExportRecord["format"])}><option value="csv">CSV · Maks. 5.000 baris</option><option value="xlsx">Excel (XLSX) · Maks. 5.000 baris</option><option value="pdf">PDF · Maks. 100 pemeriksaan</option></select></label>
            <button className="button button-primary" onClick={() => void createExport()} disabled={exporting || optionsLoading || !academicYear}><span>↓</span>{exporting ? "Mengirim permintaan..." : "Buat ekspor"}</button>
          </section>
          <section className="card report-export-history">
            <div className="report-section-heading"><div><h2>Riwayat ekspor</h2><p>File yang siap dapat diunduh ulang selama masa retensi.</p></div><button className="button button-quiet" onClick={() => setRefreshKey((current) => current + 1)}>↻ Perbarui</button></div>
            {exports.length ? <div className="report-export-list">{exports.map((item) => <div className="report-export-item" key={item.id}>
              <div><strong>{item.format.toUpperCase()} · {item.filters.academic_year}{item.filters.semester ? ` · Semester ${item.filters.semester}` : " · Tahunan"}</strong><span>{item.row_count === null ? "Jumlah baris menunggu proses" : `${item.row_count.toLocaleString("id-ID")} pemeriksaan`} · Dibuat {new Date(item.created_at).toLocaleString("id-ID")}</span>{item.failure_message && <small>{item.failure_message}</small>}</div>
              <div><span className={`export-status export-status-${item.status}`}>{({ queued: "Antrean", processing: "Diproses", completed: "Siap diunduh", failed: "Gagal", expired: "Kedaluwarsa" })[item.status]}</span>{item.can_download && <a className="button button-outline" href={`/api/laos/reports/exports/${item.id}/download`}>Unduh</a>}</div>
            </div>)}</div> : <p className="report-chart-empty">Belum ada ekspor pada cakupan akun ini.</p>}
            {historyPage < historyLastPage && <button className="button button-quiet report-history-more" onClick={() => void loadMoreExports()} disabled={historyLoading}>{historyLoading ? "Memuat..." : "Muat riwayat sebelumnya"}</button>}
          </section>
          <p className="report-snapshot-note">File berisi data kesehatan rahasia, akses unduh diperiksa ulang sesuai sekolah yang menjadi cakupan akun, dan setiap unduhan dicatat pada audit log.</p>
        </> : <section className="empty-state card"><div className="empty-icon">▤</div><h2>Rekap tidak tersedia</h2><p>Pilih tahun ajaran yang mempunyai data dalam cakupan Anda.</p></section>}
      </section>
    </section>
  );
}

function CoverageChart({ groups }: { groups: ReportGroup[] }) {
  if (!groups.length) return null;
  return <div className="coverage-chart">{groups.slice(0, 12).map((group) => <div className="coverage-chart-row" key={`${group.school_id ?? "school"}-${group.id ?? "none"}-${group.name}`}>
    <div className="coverage-chart-label"><strong>{group.name}</strong><span>{group.checked} / {group.total} siswa · {group.coverage_percent}%</span></div>
    <div className="coverage-chart-track" role="img" aria-label={`${group.name}: cakupan ${group.coverage_percent} persen`}><span style={{ width: `${Math.min(group.coverage_percent, 100)}%` }} /></div>
  </div>)}</div>;
}

function RoleShell({
  title,
  role,
  active,
  items,
  onNavigate,
  onLogout,
  children,
}: {
  title: string;
  role: string;
  active: string;
  items: readonly (readonly [string, string, string])[];
  onNavigate?: (key: string) => void;
  onLogout: () => void;
  children: ReactNode;
}) {
  return (
    <div className="app-shell">
      <aside className="sidebar">
        <Link className="brand" href="/" aria-label="LAOS beranda"><span className="brand-mark">L</span><span>LAOS<span className="brand-subtitle">LAYANAN KESEHATAN SEKOLAH</span></span></Link>
        <div className="side-label">RUANG KERJA</div>
        <nav className="side-nav" aria-label="Navigasi utama">{items.map(([key, icon, label]) => onNavigate ? <button key={key} className={active === label ? "nav-item active" : "nav-item"} onClick={() => onNavigate(key)}><span>{icon}</span>{label}</button> : <div key={key} className="nav-item active" aria-current="page"><span>{icon}</span>{label}</div>)}</nav>
        <div className="sidebar-spacer" />
        <div className="privacy-note"><span className="privacy-icon">◈</span><strong>Data terlindungi</strong><span>Akses sesuai peran dan sekolah yang ditugaskan.</span></div>
        <div className="sidebar-footer">LAOS · UKS DIGITAL</div>
      </aside>
      <main className="main-area">
        <header className="top-header"><div className="breadcrumbs"><span>Ruang kerja</span><b>/</b><strong>{active}</strong></div><div className="account"><span className="avatar">{title.slice(0, 1).toLocaleUpperCase("id")}</span><div className="account-name"><strong>{title}</strong><span>{role}</span></div><button className="text-button" onClick={onLogout}>Keluar</button></div></header>
        <div className="page-content">{children}</div>
        <footer className="app-footer"><span>LAOS · Laporan Online Sekolah</span><span>Data kesehatan siswa · Akses terbatas</span></footer>
      </main>
    </div>
  );
}

function RoleStat({ label, value, note, icon }: { label: string; value: string | number; note: string; icon: string }) {
  return <article className="stat-card card"><div className="stat-top"><span>{label}</span><span className="stat-icon">{icon}</span></div><strong>{value}</strong><small>{note}</small></article>;
}

function ProfileValue({ label, value }: { label: string; value: string }) {
  return <div className="profile-value"><span>{label}</span><strong>{value}</strong></div>;
}

function InlineAlert({ message, onClose }: { message: string; onClose: () => void }) {
  return <div className="alert" role="alert"><span>!</span><p>{message}</p><button aria-label="Tutup pesan" onClick={onClose}>×</button></div>;
}

function SchoolTableMessage({ children }: { children: string }) {
  return <tr><td className="table-message" colSpan={7}>{children}</td></tr>;
}

function SchoolExaminationTable({
  items,
  loading,
  onView,
}: {
  items: Examination[];
  loading: boolean;
  onView: (record: Examination) => void;
}) {
  return <section className="card table-card"><div className="table-scroll"><table><thead><tr><th>Siswa</th><th>Kelas</th><th>Tanggal</th><th>Pemeriksa</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    {loading ? <SchoolTableMessage>Memuat hasil pemeriksaan...</SchoolTableMessage> : items.length ? items.map((item) => <tr key={item.id}><td><strong>{item.student.name}</strong><small className="table-subtext">{item.student.nisn ?? "NISN belum diisi"}</small></td><td>{item.school_class?.name ?? "—"}</td><td>{item.examined_at}</td><td>{item.examiner_name}</td><td><span className="status-pill status-done"><i />Terpublikasi</span></td><td><button className="table-action" onClick={() => onView(item)}>Lihat hasil</button></td></tr>) : <SchoolTableMessage>Belum ada hasil pemeriksaan terpublikasi pada semester ini.</SchoolTableMessage>}
  </tbody></table></div></section>;
}

function EnrollmentModal({
  action,
  schools,
  years,
  classes,
  busy,
  onClose,
  onSubmit,
}: {
  action: { enrollment: Enrollment; action: "close" | "transfer" };
  schools: School[];
  years: AcademicYear[];
  classes: SchoolClass[];
  busy: boolean;
  onClose: () => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  const [schoolId, setSchoolId] = useState(String(action.enrollment.school_id));
  const targetYears = years.filter((year) => String(year.school_id) === schoolId);
  const [targetClasses, setTargetClasses] = useState<SchoolClass[]>(classes.filter((item) => String(item.school_id) === schoolId));
  const [classesLoading, setClassesLoading] = useState(true);
  const [classesMessage, setClassesMessage] = useState("");

  useEffect(() => {
    let active = true;
    apiFetch<unknown>(`classes?school_id=${schoolId}`)
      .then((response) => { if (active) setTargetClasses(rowsFrom<SchoolClass>(response)); })
      .catch((error: unknown) => { if (active) setClassesMessage(notice(error)); })
      .finally(() => { if (active) setClassesLoading(false); });
    return () => { active = false; };
  }, [schoolId]);

  const activeClasses = targetClasses.filter((item) => item.is_active);
  return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><section className="report-modal enrollment-modal" role="dialog" aria-modal="true" aria-labelledby="enrollment-title">
    <header className="modal-header"><div><div className="eyebrow">ADMINISTRASI SISWA</div><h2 id="enrollment-title">{action.action === "close" ? "Akhiri enrollment" : "Pindahkan siswa"}</h2><p>{action.enrollment.student.name} · {action.enrollment.academic_year?.name}</p></div><button className="icon-button" onClick={onClose} aria-label="Tutup">×</button></header>
    <form className="stack-form enrollment-form" onSubmit={onSubmit}>
      {classesMessage && <InlineAlert message={classesMessage} onClose={() => setClassesMessage("")} />}
      {action.action === "close" ? <label>Tanggal akhir enrollment<input name="ends_on" type="date" min={action.enrollment.starts_on} required defaultValue={today()} /></label> : <>
        <label>Sekolah tujuan<select name="school_id" value={schoolId} required onChange={(event) => { setSchoolId(event.target.value); setTargetClasses([]); setClassesLoading(true); setClassesMessage(""); }}>{schools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}</select></label>
        <label>Tahun ajaran tujuan<select name="academic_year_id" required defaultValue=""><option value="">Pilih tahun ajaran</option>{targetYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}</select></label>
        <label>Kelas tujuan<select name="school_class_id" defaultValue="" disabled={classesLoading}><option value="">{classesLoading ? "Memuat kelas..." : "Belum ditentukan"}</option>{activeClasses.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
        <label>Tanggal mulai di sekolah tujuan<input name="starts_on" type="date" min={action.enrollment.starts_on} required defaultValue={today()} /></label>
      </>}
      <div className="modal-footer"><span>Riwayat enrollment sebelumnya tetap tersimpan.</span><div><button type="button" className="button button-quiet" onClick={onClose}>Batal</button><button className="button button-primary" disabled={busy}>{busy ? "Menyimpan..." : "Simpan"}</button></div></div>
    </form>
  </section></div>;
}

function SchoolReportModal({ record, onClose }: { record: Examination; onClose: () => void }) {
  return <HealthDetailModal
    title={record.student.name}
    school={record.school.name}
    schoolClass={record.school_class?.name}
    examinedAt={record.examined_at}
    examiner={record.examiner_name}
    version={record.version}
    bmi={record.bmi}
    observations={record.observations}
    onClose={onClose}
  />;
}

function HealthRecordModal({ record, onClose }: { record: HealthRecord; onClose: () => void }) {
  return <HealthDetailModal
    title="Hasil pemeriksaan"
    school={record.school.name}
    schoolClass={record.school_class?.name ?? undefined}
    examinedAt={record.examined_at}
    examiner={record.examiner_name}
    version={record.version}
    bmi={record.bmi}
    observations={record.observations}
    onClose={onClose}
  />;
}

function HealthDetailModal({
  title,
  school,
  schoolClass,
  examinedAt,
  examiner,
  version,
  bmi,
  observations,
  onClose,
}: {
  title: string;
  school: string;
  schoolClass?: string;
  examinedAt: string;
  examiner: string;
  version: number;
  bmi?: string | null;
  observations: Record<string, { status: string; value: unknown }>;
  onClose: () => void;
}) {
  function displayValue(key: string) {
    const observation = observations[key];
    if (!observation || observation.status === "not_examined") return "Belum diperiksa";
    const definition = indicators.find((item) => item.key === key);
    const raw = observation.value;
    const choice = definition?.choices?.find((item) => item.value === String(raw));
    const value = choice?.label ?? (typeof raw === "boolean" ? (raw ? "Ya" : "Tidak") : String(raw ?? "—"));
    return `${value}${definition?.unit ? ` ${definition.unit}` : ""}`;
  }
  const groups = [...new Set(indicators.map((item) => item.group))];
  return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><section className="report-modal health-detail-modal" role="dialog" aria-modal="true" aria-labelledby="health-detail-title">
    <header className="modal-header"><div><div className="eyebrow">RAPOR KESEHATAN · TERPUBLIKASI</div><h2 id="health-detail-title">{title}</h2><p>{school} · {schoolClass ?? "Kelas tidak tersedia"} · {examinedAt} · {examiner}</p></div><button className="icon-button" onClick={onClose} aria-label="Tutup">×</button></header>
    <div className="report-body"><div className="report-summary"><div><span>Versi hasil</span><strong>Versi {version} · Terpublikasi</strong></div><div><span>IMT</span><strong>{bmi ?? "Belum dihitung"}</strong></div></div>
      {groups.map((group) => <section className="health-report-group" key={group}><h3>{group}</h3>{indicators.filter((item) => item.group === group).map((item) => <div className="report-row" key={item.key}><span>{item.label}</span><strong>{displayValue(item.key)}</strong></div>)}</section>)}
      <p className="health-report-note">Hasil dicatat oleh petugas pemeriksa. Tampilan ini tidak melakukan diagnosis atau klasifikasi klinis otomatis.</p>
    </div>
    <footer className="modal-footer"><span>Anda dapat mencetak atau memilih Simpan sebagai PDF pada dialog peramban.</span><div><button className="button button-outline" onClick={() => printReport("detail")}>Cetak / Simpan PDF</button><button className="button button-primary" onClick={onClose}>Tutup</button></div></footer>
  </section></div>;
}
