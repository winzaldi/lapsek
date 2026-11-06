"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { apiFetch, formatApiError, rowsFrom } from "@/lib/api";

export type AdminTab = "overview" | "centers" | "schools" | "periods" | "accounts" | "students" | "reports";
type HealthCenter = { id: number; code: string; name: string; address?: string | null; phone?: string | null; is_active: boolean };
type School = { id: number; health_center_id: number; npsn?: string | null; name: string; level: string; school_type?: string; address?: string | null; is_active: boolean; health_center?: { id: number; name: string } };
type AcademicYear = { id: number; school_id: number; name: string; starts_on: string; ends_on: string; is_active: boolean };
type Semester = { id: number; academic_year_id: number; number: number; starts_on: string; ends_on: string };
type Role = { id?: number; name: string; label?: string; pivot?: { scope_type?: string | null; scope_id?: number | null } };
type User = { id: number; name: string; email: string; is_active: boolean; roles: Role[] };
type SchoolClass = { id: number; school_id: number; name: string; grade: number; academic_level: string; is_active: boolean };
type Student = {
  id: number;
  name: string;
  nisn?: string | null;
  birth_date?: string | null;
  sex?: string | null;
  enrollments?: { id: number; ends_on: string | null }[];
};

const roleLabels: Record<string, string> = {
  system_admin: "Admin Sistem",
  puskesmas_operator: "Operator Puskesmas",
  school_operator: "Operator Sekolah/Guru",
  student: "Siswa",
};

const levelLabels: Record<string, string> = { sd: "SD/Sederajat", smp: "SMP/Sederajat", sma: "SMA/Sederajat", slb: "SLB" };

function unwrap<T>(value: unknown) {
  return rowsFrom<T>(value);
}

function activeLabel(active: boolean) {
  return active ? "Aktif" : "Nonaktif";
}

function userId(users: User[], roleName: string) {
  return users.find((user) => user.roles.some((role) => role.name === roleName))?.id;
}

export default function AdminWorkspace({
  tab,
  onMessage,
  onNavigate,
}: {
  tab: AdminTab;
  onMessage: (message: string) => void;
  onNavigate: (tab: AdminTab) => void;
}) {
  const [centers, setCenters] = useState<HealthCenter[]>([]);
  const [schools, setSchools] = useState<School[]>([]);
  const [years, setYears] = useState<AcademicYear[]>([]);
  const [semesters, setSemesters] = useState<Semester[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [classes, setClasses] = useState<SchoolClass[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [newAccountRole, setNewAccountRole] = useState("");
  const [studentSchoolId, setStudentSchoolId] = useState("");

  const fetchData = useCallback(async () => {
    const [centerResponse, schoolResponse, yearResponse, semesterResponse, userResponse] = await Promise.all([
      apiFetch<unknown>("health-centers"),
      apiFetch<unknown>("schools"),
      apiFetch<unknown>("academic-years"),
      apiFetch<unknown>("semesters"),
      apiFetch<unknown>("users"),
    ]);
    return {
      centers: unwrap<HealthCenter>(centerResponse),
      schools: unwrap<School>(schoolResponse),
      years: unwrap<AcademicYear>(yearResponse),
      semesters: unwrap<Semester>(semesterResponse),
      users: unwrap<User>(userResponse),
    };
  }, []);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchData();
      setCenters(data.centers);
      setSchools(data.schools);
      setYears(data.years);
      setSemesters(data.semesters);
      setUsers(data.users);
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setLoading(false);
    }
  }, [fetchData, onMessage]);

  useEffect(() => {
    let active = true;
    fetchData().then((data) => {
      if (!active) return;
      setCenters(data.centers);
      setSchools(data.schools);
      setYears(data.years);
      setSemesters(data.semesters);
      setUsers(data.users);
      onMessage("");
    }).catch((error: unknown) => {
      if (active) onMessage(formatApiError(error));
    }).finally(() => {
      if (active) setLoading(false);
    });
    return () => { active = false; };
  }, [fetchData, onMessage]);

  useEffect(() => {
    if ((tab !== "students" && tab !== "accounts") || !schools.length) return;
    let active = true;
    Promise.all(schools.map(async (school) => {
      const [studentResponse, classResponse] = await Promise.all([
        apiFetch<unknown>(`students?school_id=${school.id}`),
        apiFetch<unknown>(`classes?school_id=${school.id}`),
      ]);
      return {
        students: unwrap<Student>(studentResponse),
        classes: unwrap<SchoolClass>(classResponse),
      };
    })).then((schoolData) => {
      if (!active) return;
      setStudents(schoolData.flatMap((item) => item.students));
      setClasses(schoolData.flatMap((item) => item.classes));
      setStudentSchoolId((current) => current || String(schools[0].id));
    }).catch((error: unknown) => onMessage(formatApiError(error)));
    return () => { active = false; };
  }, [tab, schools, onMessage]);

  async function submit(
    event: FormEvent<HTMLFormElement>,
    endpoint: string,
    method: "POST" | "PATCH",
    successMessage: string,
  ) {
    event.preventDefault();
    const formElement = event.currentTarget;
    const form = new FormData(formElement);
    const payload = Object.fromEntries(form.entries());
    setBusy(true);
    onMessage("");
    try {
      await apiFetch(endpoint, { method, body: JSON.stringify(payload) });
      formElement.reset();
      onMessage(successMessage);
      await loadData();
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function toggleActive(endpoint: string, active: boolean, label: string) {
    setBusy(true);
    onMessage("");
    try {
      await apiFetch(endpoint, { method: "PATCH", body: JSON.stringify({ is_active: !active }) });
      onMessage(`${label} berhasil ${active ? "dinonaktifkan" : "diaktifkan"}.`);
      await loadData();
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function updateUserRole(event: FormEvent<HTMLFormElement>, user: User) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const role = String(form.get("role"));
    const rawScope = String(form.get("scope_id") ?? "");
    const [scopeType, scopeId] = rawScope.split(":");
    const expectedScope = role === "puskesmas_operator" ? "puskesmas" : role === "school_operator" ? "school" : null;
    if (expectedScope && scopeType !== expectedScope) {
      onMessage("Pilih cakupan yang sesuai dengan peran baru.");
      return;
    }
    setBusy(true);
    onMessage("");
    try {
      await apiFetch(`users/${user.id}/role`, {
        method: "PUT",
        body: JSON.stringify({
          role,
          scope_id: role === "puskesmas_operator" || role === "school_operator"
            ? Number(scopeId)
            : null,
        }),
      });
      onMessage(`Peran ${user.name} berhasil diperbarui.`);
      await loadData();
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function submitAccount(
    formElement: HTMLFormElement,
    role: string,
    scopeType: string,
    scopeId: string,
    studentId: string,
  ) {
    const form = new FormData(formElement);
    const expectedScope = role === "puskesmas_operator" ? "puskesmas" : role === "school_operator" ? "school" : null;
    if (expectedScope && scopeType !== expectedScope) {
      onMessage("Pilih cakupan yang sesuai dengan peran akun.");
      return;
    }
    if (role === "student" && (!studentId || !form.get("identity_verified"))) {
      onMessage("Pilih profil siswa dan konfirmasi bahwa identitasnya sudah diverifikasi.");
      return;
    }
    setBusy(true);
    onMessage("");
    let accountWasCreated = false;
    try {
      const userResponse = await apiFetch<{ data: User }>("users", {
        method: "POST",
        body: JSON.stringify({
          name: form.get("name"),
          email: form.get("email"),
          password: form.get("password"),
          password_confirmation: form.get("password_confirmation"),
          role,
          scope_id: role === "puskesmas_operator" || role === "school_operator"
            ? Number(scopeId)
            : null,
        }),
      });
      accountWasCreated = true;
      if (role === "student") {
        const linkResponse = await apiFetch<{ data: { id: number } }>("student-account-links", {
          method: "POST",
          body: JSON.stringify({ student_id: Number(studentId), user_id: userResponse.data.id }),
        });
        await apiFetch(`student-account-links/${linkResponse.data.id}/verify`, {
          method: "POST",
          body: "{}",
        });
      }
      formElement.reset();
      setNewAccountRole("");
      onMessage(role === "student"
        ? "Akun siswa berhasil dibuat, ditautkan, dan diverifikasi."
        : `Akun berhasil dibuat${scopeType ? ` dengan cakupan ${scopeType}` : ""}.`);
      await loadData();
    } catch (error) {
      const detail = formatApiError(error);
      if (accountWasCreated) {
        await loadData();
        onMessage(`Akun berhasil dibuat, tetapi penautan atau verifikasi siswa gagal: ${detail}`);
      } else {
        onMessage(detail);
      }
    } finally {
      setBusy(false);
    }
  }

  async function createStudent(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const schoolId = Number(form.get("school_id"));
    setBusy(true);
    onMessage("");
    try {
      await apiFetch("students", {
        method: "POST",
        body: JSON.stringify({
          school_id: schoolId,
          academic_year_id: Number(form.get("academic_year_id")),
          school_class_id: form.get("school_class_id") ? Number(form.get("school_class_id")) : null,
          name: form.get("name"),
          nisn: form.get("nisn") || null,
          birth_date: form.get("birth_date") || null,
          sex: form.get("sex") || null,
          starts_on: form.get("starts_on"),
        }),
      });
      event.currentTarget.reset();
      setStudents(unwrap(await apiFetch<unknown>(`students?school_id=${schoolId}`)));
      onMessage("Siswa dan enrollment berhasil dibuat.");
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  async function createClass(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const school = schools.find((item) => item.id === Number(form.get("school_id")));
    if (!school) return;
    setBusy(true);
    onMessage("");
    try {
      await apiFetch("classes", {
        method: "POST",
        body: JSON.stringify({
          school_id: school.id,
          name: form.get("name"),
          grade: Number(form.get("grade")),
          academic_level: school.level,
        }),
      });
      event.currentTarget.reset();
      setClasses(unwrap(await apiFetch<unknown>(`classes?school_id=${school.id}`)));
      onMessage("Kelas berhasil dibuat.");
    } catch (error) {
      onMessage(formatApiError(error));
    } finally {
      setBusy(false);
    }
  }

  const pageTitles: Record<AdminTab, { eyebrow: string; title: string; description: string }> = {
    overview: { eyebrow: "ADMINISTRASI SISTEM", title: "Ringkasan sistem", description: "Kelola akun, Puskesmas, sekolah, dan periode pemeriksaan dari satu tempat." },
    centers: { eyebrow: "MASTER WILAYAH", title: "Puskesmas", description: "Buat dan kelola Puskesmas yang membina sekolah." },
    schools: { eyebrow: "MASTER SEKOLAH", title: "Sekolah", description: "Daftarkan sekolah beserta jenjang dan Puskesmas pembinanya." },
    periods: { eyebrow: "KALENDER AKADEMIK", title: "Tahun ajaran & semester", description: "Atur periode pemeriksaan untuk setiap sekolah." },
    accounts: { eyebrow: "AKSES PENGGUNA", title: "Akun & penugasan", description: "Buat akun, tetapkan peran dan cakupan, serta kelola status akses." },
    students: { eyebrow: "DATA PENDIDIKAN", title: "Siswa & kelas", description: "Kelola data siswa, enrollment, dan kelas sekolah." },
    reports: { eyebrow: "LAPORAN", title: "Rekap pemeriksaan", description: "Lihat rekap dan unduh hasil pemeriksaan sesuai cakupan." },
  };
  const page = pageTitles[tab];
  const selectedStudentSchool = schools.find((school) => String(school.id) === studentSchoolId);
  const studentSchoolYears = years.filter((year) => String(year.school_id) === studentSchoolId);
  const studentSchoolClasses = classes.filter((schoolClass) => String(schoolClass.school_id) === studentSchoolId);

  return (
    <>
      <section className="page-heading">
        <div><div className="eyebrow">{page.eyebrow}</div><h1>{page.title}</h1><p>{page.description}</p></div>
        <button className="button button-quiet" onClick={() => void loadData()} disabled={loading || busy}><span aria-hidden="true">↻</span> Perbarui data</button>
      </section>

      {tab === "overview" && (
        <>
          <section className="stat-grid">
            <AdminStat label="Puskesmas" value={centers.length} note={`${centers.filter((item) => item.is_active).length} aktif`} icon="✚" />
            <AdminStat label="Sekolah" value={schools.length} note={`${schools.filter((item) => item.is_active).length} aktif`} icon="⌂" />
            <AdminStat label="Akun pengguna" value={users.length} note={`${users.filter((item) => item.is_active).length} aktif`} icon="♧" />
            <AdminStat label="Periode semester" value={semesters.length} note={`${years.length} tahun ajaran`} icon="◷" />
          </section>
          <section className="card admin-welcome">
            <div className="admin-welcome-icon">◈</div>
            <div><h2>Selamat datang, Admin Sistem</h2><p>Akun Admin Sistem memiliki akses penuh ke data dan penugasan. Mulai dengan mendaftarkan Puskesmas, lalu sekolah, periode akademik, dan akun operator.</p></div>
            <div className="admin-setup-steps"><span><b>1</b>Puskesmas</span><i>→</i><span><b>2</b>Sekolah</span><i>→</i><span><b>3</b>Periode</span><i>→</i><span><b>4</b>Akun operator</span></div>
          </section>
          <div className="section-heading"><div><h2>Akses cepat</h2><p>Pilih modul administrasi yang ingin dikelola.</p></div></div>
          <section className="admin-shortcuts">
            {([
              ["centers", "✚", "Puskesmas", "Kelola wilayah layanan"],
              ["schools", "⌂", "Sekolah", "Tambah sekolah dan jenjang"],
              ["periods", "◷", "Tahun ajaran & semester", "Atur periode pemeriksaan"],
              ["accounts", "♧", "Akun & penugasan", "Buat operator dan atur akses"],
              ["students", "▦", "Siswa & kelas", "Kelola data pendidikan"],
            ] as const).map(([target, icon, title, description]) => (
              <button className="admin-shortcut card" key={target} onClick={() => onNavigate(target)}>
                <span>{icon}</span><strong>{title}</strong><small>{description}</small><b>Kelola →</b>
              </button>
            ))}
          </section>
        </>
      )}

      {tab === "centers" && (
        <div className="admin-layout">
          <section className="card form-card">
            <AdminCardTitle icon="✚" title="Tambah Puskesmas" description="Kode harus unik dan hanya menggunakan huruf, angka, tanda hubung, atau garis bawah." />
            <form className="stack-form" onSubmit={(event) => void submit(event, "health-centers", "POST", "Puskesmas berhasil dibuat.")}>
              <label>Kode Puskesmas<input name="code" required maxLength={32} pattern="[A-Za-z0-9_-]+" placeholder="Contoh: PKM-MELATI" /></label>
              <label>Nama Puskesmas<input name="name" required maxLength={255} /></label>
              <label>Alamat <span className="optional">opsional</span><textarea name="address" maxLength={500} /></label>
              <label>Nomor telepon <span className="optional">opsional</span><input name="phone" maxLength={32} /></label>
              <button className="button button-primary" disabled={busy}>Simpan Puskesmas</button>
            </form>
          </section>
          <section className="card table-card">
            <AdminTableTitle title="Daftar Puskesmas" count={centers.length} />
            <div className="table-scroll"><table><thead><tr><th>Puskesmas</th><th>Kode</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
              {loading ? <AdminEmpty>Memuat data...</AdminEmpty> : centers.length ? centers.map((center) => (
                <tr key={center.id}><td><strong>{center.name}</strong><small className="table-subtext">{center.address ?? "Alamat belum diisi"}</small></td><td>{center.code}</td><td><StatusTag active={center.is_active} /></td><td><button className="table-action" disabled={busy} onClick={() => void toggleActive(`health-centers/${center.id}`, center.is_active, center.name)}>{center.is_active ? "Nonaktifkan" : "Aktifkan"}</button></td></tr>
              )) : <AdminEmpty>Belum ada Puskesmas.</AdminEmpty>}
            </tbody></table></div>
          </section>
        </div>
      )}

      {tab === "schools" && (
        <div className="admin-layout">
          <section className="card form-card">
            <AdminCardTitle icon="⌂" title="Tambah sekolah" description="Setiap sekolah harus terhubung ke Puskesmas pembina." />
            <form className="stack-form" onSubmit={(event) => void submit(event, "schools", "POST", "Sekolah berhasil dibuat.")}>
              <label>Nama sekolah<input name="name" required maxLength={255} /></label>
              <div className="form-row"><label>NPSN <span className="optional">opsional</span><input name="npsn" maxLength={16} /></label><label>Jenjang<select name="level" required defaultValue=""><option value="">Pilih jenjang</option>{Object.entries(levelLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label></div>
              <label>Puskesmas pembina<select name="health_center_id" required defaultValue=""><option value="">Pilih Puskesmas</option>{centers.filter((item) => item.is_active).map((center) => <option key={center.id} value={center.id}>{center.name}</option>)}</select></label>
              <label>Jenis sekolah<select name="school_type" defaultValue="regular"><option value="regular">Reguler</option><option value="equivalent">Sederajat</option><option value="special_needs">Kebutuhan khusus</option></select></label>
              <label>Alamat <span className="optional">opsional</span><textarea name="address" maxLength={500} /></label>
              <button className="button button-primary" disabled={busy || !centers.length}>Simpan sekolah</button>
              {!centers.length && <small className="form-hint">Daftarkan Puskesmas terlebih dahulu.</small>}
            </form>
          </section>
          <section className="card table-card">
            <AdminTableTitle title="Daftar sekolah" count={schools.length} />
            <div className="table-scroll"><table><thead><tr><th>Sekolah</th><th>Jenjang</th><th>Puskesmas</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
              {loading ? <AdminEmpty>Memuat data...</AdminEmpty> : schools.length ? schools.map((school) => (
                <tr key={school.id}><td><strong>{school.name}</strong><small className="table-subtext">{school.npsn ? `NPSN ${school.npsn}` : "NPSN belum diisi"}</small></td><td>{levelLabels[school.level] ?? school.level}</td><td>{school.health_center?.name ?? centers.find((item) => item.id === school.health_center_id)?.name ?? "—"}</td><td><StatusTag active={school.is_active} /></td><td><button className="table-action" disabled={busy} onClick={() => void toggleActive(`schools/${school.id}`, school.is_active, school.name)}>{school.is_active ? "Nonaktifkan" : "Aktifkan"}</button></td></tr>
              )) : <AdminEmpty>Belum ada sekolah.</AdminEmpty>}
            </tbody></table></div>
          </section>
        </div>
      )}

      {tab === "periods" && (
        <div className="admin-workflow">
          <div className="admin-layout">
            <section className="card form-card">
              <AdminCardTitle icon="◷" title="Tambah tahun ajaran" description="Contoh nama tahun ajaran: 2026-2027. Rentang tanggal tidak boleh tumpang tindih." />
              <form className="stack-form" onSubmit={(event) => void submit(event, "academic-years", "POST", "Tahun ajaran berhasil dibuat.")}>
                <label>Sekolah<select name="school_id" required defaultValue=""><option value="">Pilih sekolah</option>{schools.filter((item) => item.is_active).map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}</select></label>
                <label>Nama tahun ajaran<input name="name" required pattern="\d{4}-\d{4}" maxLength={16} placeholder="2026-2027" /></label>
                <div className="form-row"><label>Mulai<input name="starts_on" type="date" required /></label><label>Selesai<input name="ends_on" type="date" required /></label></div>
                <label className="checkbox-label"><input name="is_active" type="checkbox" value="1" /> Jadikan tahun ajaran aktif</label>
                <button className="button button-primary" disabled={busy || !schools.length}>Simpan tahun ajaran</button>
              </form>
            </section>
            <section className="card form-card">
              <AdminCardTitle icon="◷" title="Tambah semester" description="Tanggal semester harus berada di dalam tahun ajaran." />
              <form className="stack-form" onSubmit={(event) => void submit(event, "semesters", "POST", "Semester berhasil dibuat.")}>
                <label>Tahun ajaran<select name="academic_year_id" required defaultValue=""><option value="">Pilih tahun ajaran</option>{years.map((year) => <option key={year.id} value={year.id}>{schools.find((school) => school.id === year.school_id)?.name} · {year.name}</option>)}</select></label>
                <label>Semester<select name="number" required defaultValue=""><option value="">Pilih semester</option><option value="1">Semester 1</option><option value="2">Semester 2</option></select></label>
                <div className="form-row"><label>Mulai<input name="starts_on" type="date" required /></label><label>Selesai<input name="ends_on" type="date" required /></label></div>
                <button className="button button-primary" disabled={busy || !years.length}>Simpan semester</button>
              </form>
            </section>
          </div>
          <div className="admin-layout">
            <section className="card table-card"><AdminTableTitle title="Tahun ajaran" count={years.length} /><div className="table-scroll"><table><thead><tr><th>Sekolah</th><th>Tahun ajaran</th><th>Periode</th><th>Status</th></tr></thead><tbody>{years.map((year) => <tr key={year.id}><td>{schools.find((school) => school.id === year.school_id)?.name ?? "—"}</td><td><strong>{year.name}</strong></td><td>{year.starts_on} — {year.ends_on}</td><td><StatusTag active={year.is_active} /></td></tr>)}{!years.length && <AdminEmpty>Belum ada tahun ajaran.</AdminEmpty>}</tbody></table></div></section>
            <section className="card table-card"><AdminTableTitle title="Semester" count={semesters.length} /><div className="table-scroll"><table><thead><tr><th>Sekolah</th><th>Periode</th><th>Tanggal</th></tr></thead><tbody>{semesters.map((semester) => { const year = years.find((item) => item.id === semester.academic_year_id); return <tr key={semester.id}><td>{schools.find((school) => school.id === year?.school_id)?.name ?? "—"}</td><td><strong>{year?.name ?? "—"} · Semester {semester.number}</strong></td><td>{semester.starts_on} — {semester.ends_on}</td></tr>; })}{!semesters.length && <AdminEmpty>Belum ada semester.</AdminEmpty>}</tbody></table></div></section>
          </div>
        </div>
      )}

      {tab === "accounts" && (
        <div className="admin-workflow">
          <section className="card form-card account-create-card">
            <AdminCardTitle icon="♧" title="Buat akun pengguna" description="Kata sandi minimal 12 karakter. Akun dibuat aktif dan langsung mendapat peran/cakupan yang dipilih." />
            <form className="account-create-form" onSubmit={(event) => {
              const formElement = event.currentTarget;
              const form = new FormData(formElement);
              const role = String(form.get("role"));
              const [scopeType, scopeId] = String(form.get("scope_id") ?? "").split(":");
              event.preventDefault();
              void submitAccount(formElement, role, scopeType, scopeId, String(form.get("student_id") ?? ""));
            }}>
              <label>Nama lengkap<input name="name" required maxLength={255} /></label>
              <label>Email<input name="email" type="email" required maxLength={255} /></label>
              <label>Peran<select name="role" required value={newAccountRole} onChange={(event) => setNewAccountRole(event.target.value)}><option value="">Pilih peran</option>{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>
              {(newAccountRole === "puskesmas_operator" || newAccountRole === "school_operator") && (
                <label>{newAccountRole === "puskesmas_operator" ? "Puskesmas penugasan" : "Sekolah penugasan"}
                  <select name="scope_id" required defaultValue="">
                    <option value="">Pilih cakupan</option>
                    {(newAccountRole === "puskesmas_operator" ? centers : schools).map((item) => (
                      <option key={item.id} value={`${newAccountRole === "puskesmas_operator" ? "puskesmas" : "school"}:${item.id}`}>{item.name}</option>
                    ))}
                  </select>
                </label>
              )}
              {newAccountRole === "student" && (
                <>
                  <label>Profil siswa<select name="student_id" required defaultValue=""><option value="">Pilih profil siswa</option>{students.filter((student) => student.enrollments?.length).map((student) => <option key={student.id} value={student.id}>{student.name}{student.nisn ? ` · NISN ${student.nisn}` : ""}</option>)}</select></label>
                  <label className="checkbox-label"><input name="identity_verified" type="checkbox" value="1" required /> Saya telah memverifikasi identitas siswa melalui prosedur sekolah</label>
                </>
              )}
              <label>Kata sandi sementara<input name="password" type="password" required minLength={12} autoComplete="new-password" /></label>
              <label>Ulangi kata sandi<input name="password_confirmation" type="password" required minLength={12} autoComplete="new-password" /></label>
              <button className="button button-primary" disabled={busy || !newAccountRole}>Buat akun</button>
              <p className="form-hint">Untuk akun Siswa, pilih profil dan konfirmasi verifikasi identitas sebelum akun ditautkan.</p>
            </form>
          </section>
          <section className="card table-card">
            <AdminTableTitle title="Akun dan penugasan" count={users.length} />
            <div className="table-scroll"><table><thead><tr><th>Pengguna</th><th>Peran</th><th>Status</th><th>Ubah peran/cakupan</th><th>Aksi</th></tr></thead><tbody>
              {loading ? <AdminEmpty>Memuat data...</AdminEmpty> : users.map((user) => {
                const currentRole = user.roles[0]?.name ?? "";
                const scopeType = user.roles[0]?.pivot?.scope_type;
                const currentScope = user.roles[0]?.pivot?.scope_id ?? "";
                return <tr key={user.id}>
                  <td><strong>{user.name}</strong><small className="table-subtext">{user.email}</small></td>
                  <td>{roleLabels[currentRole] ?? currentRole}</td>
                  <td><StatusTag active={user.is_active} /></td>
                  <td><form className="role-edit-form" onSubmit={(event) => void updateUserRole(event, user)}>
                    <select name="role" defaultValue={currentRole} aria-label={`Peran ${user.name}`} onChange={(event) => {
                      const scopeSelect = event.currentTarget.form?.elements.namedItem("scope_id");
                      if (scopeSelect instanceof HTMLSelectElement) scopeSelect.value = "";
                    }}>{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>
                    <select name="scope_id" defaultValue={scopeType && currentScope ? `${scopeType}:${currentScope}` : ""} aria-label={`Cakupan ${user.name}`}>
                      <option value="">Tanpa cakupan</option>
                      <optgroup label="Puskesmas">{centers.map((center) => <option key={`puskesmas:${center.id}`} value={`puskesmas:${center.id}`}>{center.name}</option>)}</optgroup>
                      <optgroup label="Sekolah">{schools.map((school) => <option key={`school:${school.id}`} value={`school:${school.id}`}>{school.name}</option>)}</optgroup>
                    </select>
                    <button className="table-action" disabled={busy}>Simpan</button>
                  </form></td>
                  <td><button className="table-action" disabled={busy || (user.id === userId(users, "system_admin") && user.is_active)} onClick={() => void toggleActive(`users/${user.id}/status`, user.is_active, user.name)}>{user.is_active ? "Nonaktifkan" : "Aktifkan"}</button></td>
                </tr>;
              })}
              {!users.length && !loading && <AdminEmpty>Belum ada akun.</AdminEmpty>}
            </tbody></table></div>
            <div className="table-foot"><span>Menampilkan maksimum 50 akun per halaman.</span><span>Menonaktifkan akun akan mencabut token API aktifnya.</span></div>
          </section>
        </div>
      )}

      {tab === "students" && (
        <div className="admin-workflow">
          <div className="admin-layout">
            <section className="card form-card">
              <AdminCardTitle icon="♧" title="Tambah siswa dan enrollment" description="Pilih sekolah dan tahun ajaran tempat siswa mulai terdaftar." />
              <form className="stack-form" onSubmit={(event) => void createStudent(event)}>
                <label>Sekolah<select name="school_id" required value={studentSchoolId} onChange={(event) => setStudentSchoolId(event.target.value)}><option value="">Pilih sekolah</option>{schools.filter((item) => item.is_active).map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}</select></label>
                <label>Tahun ajaran<select name="academic_year_id" required defaultValue=""><option value="">Pilih tahun ajaran</option>{studentSchoolYears.map((year) => <option key={year.id} value={year.id}>{year.name}</option>)}</select></label>
                <label>Nama lengkap<input name="name" required maxLength={255} /></label>
                <div className="form-row"><label>NISN<input name="nisn" maxLength={20} /></label><label>Tanggal lahir<input name="birth_date" type="date" /></label></div>
                <label>Kelas<select name="school_class_id" defaultValue=""><option value="">Belum ditentukan</option>{studentSchoolClasses.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
                <label>Tanggal mulai enrollment<input name="starts_on" type="date" required /></label>
                <label>Jenis kelamin<select name="sex" defaultValue=""><option value="">Pilih</option><option value="male">Laki-laki</option><option value="female">Perempuan</option></select></label>
                <button className="button button-primary" disabled={busy || !years.length}>Simpan siswa</button>
              </form>
            </section>
            <section className="card form-card">
              <AdminCardTitle icon="▦" title="Tambah kelas" description="Jenjang kelas akan mengikuti jenjang sekolah yang dipilih." />
              <form className="stack-form" onSubmit={(event) => void createClass(event)}>
                <label>Sekolah<select name="school_id" required defaultValue=""><option value="">Pilih sekolah</option>{schools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}</select></label>
                <label>Nama kelas<input name="name" required maxLength={64} placeholder="Contoh: 4-A" /></label>
                <label>Tingkat<input name="grade" type="number" min={1} max={12} required /></label>
                <button className="button button-outline" disabled={busy || !schools.length}>Simpan kelas</button>
              </form>
              <div className="class-list"><strong>Kelas terdaftar</strong>{classes.map((item) => <span key={item.id}>{item.name}<small>{schools.find((school) => school.id === item.school_id)?.name}</small></span>)}{!classes.length && <p>Pilih sekolah yang telah memiliki kelas untuk menampilkan daftar.</p>}</div>
            </section>
          </div>
          <section className="card table-card"><AdminTableTitle title={`Siswa · ${selectedStudentSchool?.name ?? "cakupan sekolah"}`} count={students.length} /><div className="table-scroll"><table><thead><tr><th>Nama</th><th>NISN</th><th>Tanggal lahir</th></tr></thead><tbody>{students.map((student) => <tr key={student.id}><td><strong>{student.name}</strong></td><td>{student.nisn ?? "—"}</td><td>{student.birth_date ?? "—"}</td></tr>)}{!students.length && <AdminEmpty>Belum ada siswa di sekolah dalam cakupan.</AdminEmpty>}</tbody></table></div></section>
        </div>
      )}
      {loading && tab === "overview" && <div className="loading-inline"><span className="spinner" /> Memuat data administrasi...</div>}
    </>
  );
}

function AdminStat({ label, value, note, icon }: { label: string; value: number; note: string; icon: string }) {
  return <article className="stat-card card"><div className="stat-top"><span>{label}</span><span className="stat-icon">{icon}</span></div><strong>{value}</strong><small>{note}</small></article>;
}

function AdminCardTitle({ icon, title, description }: { icon: string; title: string; description: string }) {
  return <div className="card-title"><span className="title-icon">{icon}</span><div><h2>{title}</h2><p>{description}</p></div></div>;
}

function AdminTableTitle({ title, count }: { title: string; count: number }) {
  return <div className="table-heading"><div><h2>{title}</h2><span>Data terdaftar di sistem.</span></div><span className="record-count">{count}</span></div>;
}

function StatusTag({ active }: { active: boolean }) {
  return <span className={`status-pill ${active ? "status-done" : "status-draft"}`}><i />{activeLabel(active)}</span>;
}

function AdminEmpty({ children }: { children: string }) {
  return <tr><td className="table-message" colSpan={6}>{children}</td></tr>;
}
