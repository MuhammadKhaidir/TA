-- Dump SQLite: Sistem Arsip Berkas KP dan TA (Fasilkom Unsri)
-- Struktur seluruh tabel + data awal. Sama isinya dengan database/database.sqlite.
-- Impor ke file baru:  sqlite3 database/database.sqlite < database/sql/arsip_kp_ta.sqlite.sql
--   atau DB Browser for SQLite: File > Import > Database from SQL file.
-- Akun contoh (kata sandi: "password") hanya untuk pengembangan.

BEGIN TRANSACTION;
CREATE TABLE "arsip" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "diarsipkan_oleh" integer not null,
  "diarsipkan_at" datetime not null,
  "catatan" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("diarsipkan_oleh") references "users"("id") on delete restrict
);
CREATE TABLE "cache" ("key" varchar not null, "value" text not null, "expiration" integer not null, primary key ("key"));
CREATE TABLE "cache_locks" ("key" varchar not null, "owner" varchar not null, "expiration" integer not null, primary key ("key"));
CREATE TABLE "dokumen_pengajuan" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "persyaratan_id" integer not null,
  "diunggah_oleh" integer not null,
  "versi" integer not null default '1',
  "terbaru" tinyint(1) not null default '1',
  "nama_asli" varchar not null,
  "path" varchar not null,
  "mime" varchar,
  "ukuran" integer not null default '0',
  "status" varchar not null default 'menunggu',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("persyaratan_id") references "persyaratan"("id") on delete restrict,
  foreign key("diunggah_oleh") references "users"("id") on delete restrict
);
CREATE TABLE "dokumen_resmi" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "diunggah_oleh" integer not null,
  "jenis" varchar not null,
  "nomor" varchar not null,
  "tanggal" date not null,
  "nama_asli" varchar not null,
  "path" varchar not null,
  "mime" varchar,
  "ukuran" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("diunggah_oleh") references "users"("id") on delete restrict
);
CREATE TABLE "failed_jobs" ("id" integer primary key autoincrement not null, "uuid" varchar not null, "connection" text not null, "queue" text not null, "payload" text not null, "exception" text not null, "failed_at" datetime not null default CURRENT_TIMESTAMP);
CREATE TABLE "job_batches" ("id" varchar not null, "name" varchar not null, "total_jobs" integer not null, "pending_jobs" integer not null, "failed_jobs" integer not null, "failed_job_ids" text not null, "options" text, "cancelled_at" integer, "created_at" integer not null, "finished_at" integer, primary key ("id"));
CREATE TABLE "jobs" ("id" integer primary key autoincrement not null, "queue" varchar not null, "payload" text not null, "attempts" integer not null, "reserved_at" integer, "available_at" integer not null, "created_at" integer not null);
CREATE TABLE "migrations" ("id" integer primary key autoincrement not null, "migration" varchar not null, "batch" integer not null);
INSERT INTO "migrations" VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO "migrations" VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO "migrations" VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO "migrations" VALUES(4,'2026_10_22_000001_create_program_studi_table',1);
INSERT INTO "migrations" VALUES(5,'2026_10_22_000002_add_data_akademik_to_users_table',1);
INSERT INTO "migrations" VALUES(6,'2026_10_22_000003_create_persyaratan_table',1);
INSERT INTO "migrations" VALUES(7,'2026_10_22_000004_create_pengajuan_table',1);
INSERT INTO "migrations" VALUES(8,'2026_10_22_000005_create_dokumen_pengajuan_table',1);
INSERT INTO "migrations" VALUES(9,'2026_10_22_000006_create_pemeriksaan_table',1);
INSERT INTO "migrations" VALUES(10,'2026_10_22_000007_create_riwayat_status_table',1);
INSERT INTO "migrations" VALUES(11,'2026_10_22_000008_create_dokumen_resmi_table',1);
INSERT INTO "migrations" VALUES(12,'2026_10_22_000009_create_arsip_table',1);
INSERT INTO "migrations" VALUES(13,'2026_10_22_000010_create_log_aktivitas_table',1);
INSERT INTO "migrations" VALUES(14,'2026_10_24_000001_add_pembimbing_to_pengajuan_table',1);
INSERT INTO "migrations" VALUES(15,'2026_10_24_000002_create_riwayat_pembimbing_table',1);
INSERT INTO "migrations" VALUES(16,'2026_10_26_000001_create_surat_balasan_table',1);
INSERT INTO "migrations" VALUES(17,'2026_11_02_000001_gabungkan_operator_ke_admin',1);
INSERT INTO "migrations" VALUES(18,'2026_11_05_000001_hapus_tabel_log_aktivitas',1);
CREATE TABLE "password_reset_tokens" ("email" varchar not null, "token" varchar not null, "created_at" datetime, primary key ("email"));
CREATE TABLE "pemeriksaan" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "dokumen_id" integer,
  "pemeriksa_id" integer not null,
  "peran" varchar not null,
  "keputusan" varchar not null,
  "catatan" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("dokumen_id") references "dokumen_pengajuan"("id") on delete cascade,
  foreign key("pemeriksa_id") references "users"("id") on delete restrict
);
CREATE TABLE "pengajuan" (
  "id" integer primary key autoincrement not null,
  "mahasiswa_id" integer not null,
  "pembimbing_id" integer,
  "jenis" varchar not null,
  "judul" varchar not null,
  "deskripsi" text,
  "instansi" varchar,
  "status" varchar not null default 'draft',
  "diajukan_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "pembimbing_pilihan_id" integer,
  "status_pembimbing" varchar not null default 'belum',
  foreign key("mahasiswa_id") references "users"("id") on delete restrict,
  foreign key("pembimbing_id") references "users"("id") on delete restrict,
  foreign key("pembimbing_pilihan_id") references "users"("id") on delete restrict
);
CREATE TABLE "persyaratan" (
  "id" integer primary key autoincrement not null,
  "jenis_pengajuan" varchar not null,
  "nama" varchar not null,
  "deskripsi" text,
  "pemeriksa" varchar not null default 'admin',
  "wajib" tinyint(1) not null default '1',
  "urutan" integer not null default '0',
  "ukuran_maks_kb" integer not null default '5120',
  "ekstensi" varchar not null default 'pdf',
  "aktif" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
INSERT INTO "persyaratan" VALUES(1,'kp','Surat Permohonan KP','Surat permohonan KP bertanda tangan.','admin',1,1,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(2,'kp','Transkrip Nilai Sementara','Transkrip nilai terbaru dari sistem akademik.','admin',1,2,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(3,'kp','KRS Semester Berjalan','Kartu Rencana Studi semester berjalan.','admin',1,3,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(4,'kp','Surat Penerimaan dari Instansi','Surat balasan atau persetujuan dari instansi tujuan KP.','admin',1,4,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(5,'kp','Proposal Kerja Praktik','Proposal KP yang akan diperiksa dosen pembimbing.','dosen',1,5,5120,'pdf,doc,docx',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(6,'ta','Transkrip Nilai','Transkrip nilai terbaru dari sistem akademik.','admin',1,1,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(7,'ta','KRS Semester Berjalan','Kartu Rencana Studi semester berjalan.','admin',1,2,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(8,'ta','Bukti Pembayaran UKT','Bukti pembayaran UKT semester berjalan.','admin',1,3,5120,'pdf,jpg,jpeg,png',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(9,'ta','Proposal Tugas Akhir','Proposal TA yang akan diperiksa dosen pembimbing.','dosen',1,4,5120,'pdf,doc,docx',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "persyaratan" VALUES(10,'ta','Lembar Persetujuan Topik','Opsional. Lembar persetujuan topik dari calon pembimbing.','dosen',0,5,5120,'pdf',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
CREATE TABLE "program_studi" (
  "id" integer primary key autoincrement not null,
  "kode" varchar not null,
  "nama" varchar not null,
  "jenjang" varchar not null default 'S1',
  "aktif" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
INSERT INTO "program_studi" VALUES(1,'TI','Teknik Informatika','S1',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "program_studi" VALUES(2,'SI','Sistem Informasi','S1',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
INSERT INTO "program_studi" VALUES(3,'SK','Sistem Komputer','S1',1,'2026-10-27 10:17:40','2026-10-27 10:17:40');
CREATE TABLE "riwayat_pembimbing" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "dosen_id" integer,
  "user_id" integer,
  "status" varchar not null,
  "catatan" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("dosen_id") references "users"("id") on delete set null,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE "riwayat_status" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "user_id" integer,
  "status_lama" varchar,
  "status_baru" varchar not null,
  "catatan" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE TABLE "sessions" ("id" varchar not null, "user_id" integer, "ip_address" varchar, "user_agent" text, "payload" text not null, "last_activity" integer not null, primary key ("id"));
CREATE TABLE "surat_balasan" (
  "id" integer primary key autoincrement not null,
  "pengajuan_id" integer not null,
  "diunggah_oleh" integer not null,
  "versi" integer not null default '1',
  "terbaru" tinyint(1) not null default '1',
  "nama_asli" varchar not null,
  "path" varchar not null,
  "mime" varchar,
  "ukuran" integer not null default '0',
  "status" varchar not null default 'menunggu',
  "diperiksa_oleh" integer,
  "diperiksa_at" datetime,
  "catatan" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("pengajuan_id") references "pengajuan"("id") on delete cascade,
  foreign key("diunggah_oleh") references "users"("id") on delete restrict,
  foreign key("diperiksa_oleh") references "users"("id") on delete set null
);
CREATE TABLE "users" (
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "nomor_induk" varchar,
  "role" varchar not null default 'mahasiswa',
  "program_studi_id" integer,
  "angkatan" integer,
  "no_hp" varchar,
  "aktif" tinyint(1) not null default '1',
  foreign key("program_studi_id") references "program_studi"("id") on delete restrict
);
INSERT INTO "users" VALUES(1,'Administrator Sistem','admin@fasilkom.test',NULL,'$2y$12$6qpxqcvI34nJDr1RCJpJIegBgPzJUUd847IDT3W/nrze5d9BR5ir6',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','ADM0001','admin',NULL,NULL,NULL,1);
INSERT INTO "users" VALUES(2,'Dr. Budi Santoso, M.Kom.','dosen1@fasilkom.test',NULL,'$2y$12$JdbJrAqPprYnSTbp0fEFAehjuqPjm3SZvcMpDX2AxWhDrxTwdjQiS',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','198001012005011001','dosen',1,NULL,NULL,1);
INSERT INTO "users" VALUES(3,'Dr. Siti Rahma, M.T.','dosen2@fasilkom.test',NULL,'$2y$12$qS9yps5T9PIgfnOz0lpL8.3iQAo7b3OHSux2e7KyqIRrnogWc3QSS',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','198203152006042001','dosen',2,NULL,NULL,1);
INSERT INTO "users" VALUES(4,'Andi Pratama','mahasiswa1@fasilkom.test',NULL,'$2y$12$QlXzERmpw8AbuFpCx4lIuuwbz2An9edTxSqkuq9o/wMnGSpxujwJ2',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','09021282126001','mahasiswa',1,2021,NULL,1);
INSERT INTO "users" VALUES(5,'Rina Lestari','mahasiswa2@fasilkom.test',NULL,'$2y$12$MpSaEQtDZeugrTqey2m/W.vYFK2RZMMyObF8.HgT3vATCU3crUmsG',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','09021282227002','mahasiswa',2,2022,NULL,1);
INSERT INTO "users" VALUES(6,'Dimas Saputra','mahasiswa3@fasilkom.test',NULL,'$2y$12$IbG8UhjP5RWvnriLnnv17uHxqzez9j9/K6sWZyCS9qf.LX1KBr.Ra',NULL,'2026-10-27 10:17:41','2026-10-27 10:17:41','09021282328003','mahasiswa',3,2023,NULL,1);
CREATE UNIQUE INDEX "users_email_unique" on "users" ("email");
CREATE UNIQUE INDEX "users_nomor_induk_unique" on "users" ("nomor_induk");
CREATE INDEX "users_role_index" on "users" ("role");
CREATE INDEX "sessions_user_id_index" on "sessions" ("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions" ("last_activity");
CREATE INDEX "cache_expiration_index" on "cache" ("expiration");
CREATE INDEX "cache_locks_expiration_index" on "cache_locks" ("expiration");
CREATE INDEX "jobs_queue_index" on "jobs" ("queue");
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs" ("uuid");
CREATE UNIQUE INDEX "program_studi_kode_unique" on "program_studi" ("kode");
CREATE INDEX "persyaratan_jenis_pengajuan_aktif_index" on "persyaratan" ("jenis_pengajuan", "aktif");
CREATE INDEX "pengajuan_mahasiswa_id_jenis_index" on "pengajuan" ("mahasiswa_id", "jenis");
CREATE INDEX "pengajuan_status_index" on "pengajuan" ("status");
CREATE INDEX "pengajuan_status_pembimbing_index" on "pengajuan" ("status_pembimbing");
CREATE INDEX "dokumen_pengajuan_terbaru_index" on "dokumen_pengajuan" ("pengajuan_id", "persyaratan_id", "terbaru");
CREATE INDEX "pemeriksaan_pemeriksa_id_created_at_index" on "pemeriksaan" ("pemeriksa_id", "created_at");
CREATE UNIQUE INDEX "arsip_pengajuan_id_unique" on "arsip" ("pengajuan_id");
CREATE INDEX "riwayat_pembimbing_pengajuan_id_status_index" on "riwayat_pembimbing" ("pengajuan_id", "status");
CREATE INDEX "surat_balasan_terbaru_index" on "surat_balasan" ("pengajuan_id", "terbaru");
DELETE FROM "sqlite_sequence";
INSERT INTO "sqlite_sequence" VALUES('program_studi',3);
INSERT INTO "sqlite_sequence" VALUES('persyaratan',10);
INSERT INTO "sqlite_sequence" VALUES('users',6);
INSERT INTO "sqlite_sequence" VALUES('migrations',18);
COMMIT;
