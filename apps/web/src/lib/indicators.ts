export type IndicatorDefinition = {
  key: string;
  label: string;
  group: string;
  type: "boolean" | "number" | "text" | "select";
  choices?: { value: string; label: string }[];
  unit?: string;
};

const yesNo = [
  { value: "true", label: "Ya" },
  { value: "false", label: "Tidak" },
];

const health = [
  { value: "healthy", label: "Sehat" },
  { value: "unhealthy", label: "Tidak sehat" },
];

export const indicators: IndicatorDefinition[] = [
  { key: "blood_pressure", label: "Tekanan darah", group: "Pemeriksaan umum", type: "text" },
  { key: "past_disease_history", label: "Riwayat penyakit dahulu", group: "Pemeriksaan umum", type: "text" },
  { key: "family_disease_history", label: "Riwayat penyakit keluarga", group: "Pemeriksaan umum", type: "text" },
  { key: "personal_hygiene", label: "Kebersihan diri", group: "Pemeriksaan umum", type: "select", choices: [
    { value: "good", label: "Baik" }, { value: "fair", label: "Cukup" }, { value: "poor", label: "Kurang" },
  ] },
  { key: "breakfast", label: "Kebiasaan sarapan", group: "Gaya hidup", type: "boolean", choices: yesNo },
  { key: "smoking", label: "Kebiasaan merokok", group: "Gaya hidup", type: "boolean", choices: yesNo },
  { key: "snacks", label: "Kebiasaan jajan", group: "Gaya hidup", type: "boolean", choices: yesNo },
  { key: "alcohol_narcotics", label: "Riwayat alkohol/NAFZA", group: "Gaya hidup", type: "boolean", choices: yesNo },
  { key: "height_cm", label: "Tinggi badan", group: "Gizi", type: "number", unit: "cm" },
  { key: "weight_kg", label: "Berat badan", group: "Gizi", type: "number", unit: "kg" },
  { key: "nutrition_status", label: "Status gizi (hasil petugas)", group: "Gizi", type: "select", choices: [
    { value: "very_thin", label: "Sangat kurus" }, { value: "thin", label: "Kurus" },
    { value: "normal", label: "Normal" }, { value: "overweight", label: "Gemuk" },
    { value: "obesity", label: "Obesitas" },
  ] },
  { key: "anemia", label: "Indikasi anemia", group: "Gizi", type: "boolean", choices: yesNo },
  { key: "eyes_external", label: "Pemeriksaan luar mata", group: "Mata", type: "select", choices: health },
  { key: "color_blindness", label: "Buta warna", group: "Mata", type: "boolean", choices: yesNo },
  { key: "uses_glasses", label: "Menggunakan kacamata", group: "Mata", type: "boolean", choices: yesNo },
  { key: "ears_external", label: "Pemeriksaan luar telinga", group: "Telinga", type: "select", choices: [
    { value: "healthy", label: "Sehat" }, { value: "infection", label: "Infeksi" }, { value: "cerumen", label: "Serumen" },
  ] },
  { key: "hearing", label: "Pendengaran", group: "Telinga", type: "select", choices: [
    { value: "normal", label: "Normal" }, { value: "impaired", label: "Terganggu" },
  ] },
  { key: "cleft_lip_palate", label: "Celah bibir/langit-langit", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "mouth_corner_lesion", label: "Lesi sudut mulut", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "mouth_ulcer", label: "Sariawan", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "dirty_tongue", label: "Lidah kotor", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "other_lesion", label: "Lesi lain pada mulut", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "dental_caries", label: "Karies gigi", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "bleeding_gums", label: "Gusi berdarah", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "swollen_gums", label: "Gusi bengkak", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "dental_plaque", label: "Plak gigi", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "tartar", label: "Karang gigi", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
  { key: "irregular_front_teeth", label: "Susunan gigi depan tidak teratur", group: "Gigi dan mulut", type: "boolean", choices: yesNo },
];

export type Observation = { status: "recorded" | "not_examined"; value: string | number | boolean | null };
export type Observations = Record<string, Observation>;

export function emptyObservations(): Observations {
  return Object.fromEntries(indicators.map(({ key }) => [
    key,
    { status: "not_examined", value: null },
  ]));
}

export function normalizeObservations(input?: Record<string, { status?: string; value?: unknown }>): Observations {
  const result = emptyObservations();
  for (const indicator of indicators) {
    const observation = input?.[indicator.key];
    if (!observation) continue;
    result[indicator.key] = {
      status: observation.status === "recorded" ? "recorded" : "not_examined",
      value: observation.value === null || observation.value === undefined
        ? null
        : String(observation.value),
    };
  }
  return result;
}
