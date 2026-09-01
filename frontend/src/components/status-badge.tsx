"use client";

import { useI18n } from "@/lib/i18n";

const labels = {
  vi: { ACTIVE: "HOẠT ĐỘNG", DRAINING: "ĐANG DRAIN", MAINTENANCE: "BẢO TRÌ", FULL: "ĐẦY", OFFLINE: "OFFLINE", DISABLED: "VÔ HIỆU", BETA: "BETA", RUNNING: "ĐANG CHẠY", FAILED: "THẤT BẠI", SUSPENDED: "TẠM NGƯNG", QUEUED: "ĐANG CHỜ", BUILDING: "ĐANG BUILD", DEPLOYING: "ĐANG DEPLOY", CREATED: "ĐÃ TẠO", STOPPED: "ĐÃ DỪNG", CONNECTED: "ĐÃ KẾT NỐI", DISCONNECTED: "MẤT KẾT NỐI", REVOKED: "ĐÃ THU HỒI", SECRET: "BÍ MẬT", PLAIN: "THƯỜNG", VERIFIED: "ĐÃ XÁC MINH", PENDING: "ĐANG CHỜ" },
  en: { ACTIVE: "ACTIVE", DRAINING: "DRAINING", MAINTENANCE: "MAINTENANCE", FULL: "FULL", OFFLINE: "OFFLINE", DISABLED: "DISABLED", BETA: "BETA", RUNNING: "RUNNING", FAILED: "FAILED", SUSPENDED: "SUSPENDED", QUEUED: "QUEUED", BUILDING: "BUILDING", DEPLOYING: "DEPLOYING", CREATED: "CREATED", STOPPED: "STOPPED", CONNECTED: "CONNECTED", DISCONNECTED: "DISCONNECTED", REVOKED: "REVOKED", SECRET: "SECRET", PLAIN: "PLAIN", VERIFIED: "VERIFIED", PENDING: "PENDING" },
} as const;

export function StatusBadge({ status }: { status: string }) {
  const { locale } = useI18n();
  const normalized = status.toUpperCase();
  const label = labels[locale][normalized as keyof (typeof labels)[typeof locale]] ?? normalized.replaceAll("_", " ");
  return <span className={`status status-${status.toLowerCase()}`}><i />{label}</span>;
}
