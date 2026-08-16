<?php
declare(strict_types=1);

// Horário oficial de Brasília usado pela BAMAB.
date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'BAMAB';
const APP_VERSION = '3.4.4';
const FREE_HOSTING_MODE = true;
const FREE_HOSTING_PROVIDER = 'InfinityFree';
const FREE_DB_WARN_BYTES = 8 * 1024 * 1024;
const FREE_DB_FILE_LIMIT_BYTES = 10 * 1024 * 1024;
const DB_PATH = __DIR__ . '/data/bamab.sqlite';
const UPLOAD_ROOT = __DIR__ . '/uploads';
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
const MAX_VIDEO_BYTES = 8 * 1024 * 1024;

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');
    session_set_cookie_params([
        'httponly'=>true,
        'samesite'=>'Lax',
        'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'path'=>'/'
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    if (!is_dir(dirname(DB_PATH))) mkdir(dirname(DB_PATH),0775,true);
    $pdo=new PDO('sqlite:'.DB_PATH,null,null,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys=ON');
    migrate($pdo);
    return $pdo;
}


function db_column_exists(PDO $pdo,string $table,string $column): bool {
    $rows=$pdo->query("PRAGMA table_info(".$table.")")->fetchAll();
    foreach($rows as $r) if(($r['name']??'')===$column) return true;
    return false;
}
function db_add_column(PDO $pdo,string $table,string $definition): void {
    $column=preg_split('/\s+/',trim($definition))[0] ?? '';
    if($column!=='' && !db_column_exists($pdo,$table,$column)){
        $pdo->exec("ALTER TABLE ".$table." ADD COLUMN ".$definition);
    }
}

function migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings(
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL DEFAULT ''
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        display_name TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS achievements(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        year TEXT DEFAULT '',
        title TEXT NOT NULL,
        competition TEXT DEFAULT '',
        result TEXT DEFAULT '',
        description TEXT DEFAULT '',
        image_path TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 100,
        published INTEGER DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS people(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        role TEXT DEFAULT '',
        group_type TEXT NOT NULL DEFAULT 'INTEGRANTE',
        biography TEXT DEFAULT '',
        photo_path TEXT DEFAULT '',
        instagram_url TEXT DEFAULT '',
        sort_order INTEGER DEFAULT 100,
        published INTEGER DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS media(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT DEFAULT '',
        caption TEXT DEFAULT '',
        media_type TEXT NOT NULL DEFAULT 'IMAGE',
        file_path TEXT DEFAULT '',
        instagram_url TEXT DEFAULT '',
        event_date TEXT DEFAULT '',
        featured INTEGER DEFAULT 0,
        sort_order INTEGER DEFAULT 100,
        published INTEGER DEFAULT 1,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        summary TEXT DEFAULT '',
        content TEXT DEFAULT '',
        cover_path TEXT DEFAULT '',
        published INTEGER DEFAULT 1,
        created_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS agenda(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        event_date TEXT NOT NULL,
        location TEXT DEFAULT '',
        description TEXT DEFAULT '',
        published INTEGER DEFAULT 1
    )");
    // V2.9.0 — Agenda Interativa BAMAB.
    db_add_column($pdo,'agenda',"event_time TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'agenda',"end_time TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'agenda',"event_type TEXT NOT NULL DEFAULT 'OUTRO'");
    db_add_column($pdo,'agenda',"area TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'agenda',"featured INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'agenda',"created_at TEXT NOT NULL DEFAULT ''");

    // V2.9.11 — parceiros, patrocinadores, apoiadores e idealizadores; painel embutido no fim da página, alinhado à direita.
    $pdo->exec("CREATE TABLE IF NOT EXISTS sponsors(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'PATROCINADOR',
        logo_path TEXT NOT NULL DEFAULT '',
        website_url TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        duration_seconds INTEGER NOT NULL DEFAULT 6,
        sort_order INTEGER NOT NULL DEFAULT 100,
        logo_scale INTEGER NOT NULL DEFAULT 90,
        background_color TEXT NOT NULL DEFAULT '#ffffff',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sponsors_active_sort ON sponsors(active,sort_order,id)");

    // V2.9.12 — apoio institucional: Prefeitura, Secretarias e órgãos públicos/parceiros.
    $pdo->exec("CREATE TABLE IF NOT EXISTS institutional_supporters(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'PREFEITURA',
        logo_path TEXT NOT NULL DEFAULT '',
        website_url TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        duration_seconds INTEGER NOT NULL DEFAULT 7,
        sort_order INTEGER NOT NULL DEFAULT 100,
        logo_scale INTEGER NOT NULL DEFAULT 90,
        background_color TEXT NOT NULL DEFAULT '#ffffff',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_institutional_supporters_active_sort ON institutional_supporters(active,sort_order,id)");

    // V3.1.0 — canais oficiais e secundários de contato, inclusive WhatsApp.
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_channels(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        label TEXT NOT NULL,
        contact_type TEXT NOT NULL DEFAULT 'TELEFONE',
        contact_value TEXT NOT NULL DEFAULT '',
        link_url TEXT NOT NULL DEFAULT '',
        whatsapp_message TEXT NOT NULL DEFAULT '',
        channel_level TEXT NOT NULL DEFAULT 'OFICIAL',
        description TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 100,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contact_channels_level_sort ON contact_channels(active,channel_level,sort_order,id)");

    // V3.1.0 — certificados e diplomas de reconhecimento para alunos e equipe.
    $pdo->exec("CREATE TABLE IF NOT EXISTS recognition_documents(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        person_type TEXT NOT NULL,
        person_id INTEGER NOT NULL,
        document_type TEXT NOT NULL,
        recognition_year INTEGER NOT NULL,
        serial_number TEXT NOT NULL UNIQUE,
        verification_token TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'ATIVO',
        notes TEXT NOT NULL DEFAULT '',
        issued_at TEXT NOT NULL,
        issued_by INTEGER DEFAULT NULL,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(person_type,person_id,document_type,recognition_year)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_recognition_person ON recognition_documents(person_type,person_id,recognition_year)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_recognition_year_type ON recognition_documents(recognition_year,document_type,status)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recognition_signers(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL,
        signature_path TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 100,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )");
    if((int)$pdo->query("SELECT COUNT(*) FROM recognition_signers")->fetchColumn()===0){
        $sig=$pdo->prepare("INSERT INTO recognition_signers(name,role,signature_path,sort_order,active,created_at,updated_at) VALUES('',?,'',?,1,?,?)");
        foreach([
            ['COORDENADOR(A) GERAL — BAMAB',10],
            ['REPRESENTANTE DA COORDENAÇÃO — BAMAB',20],
            ['SECRETÁRIO(A) MUNICIPAL DE CULTURA',30],
            ['PREFEITO(A) MUNICIPAL',40],
        ] as $sg){$sig->execute([$sg[0],$sg[1],now_iso(),now_iso()]);}
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        protocol TEXT NOT NULL UNIQUE,
        public_token TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'PENDENTE',
        student_name TEXT NOT NULL,
        birth_date TEXT NOT NULL,
        student_cpf TEXT DEFAULT '',
        student_phone TEXT DEFAULT '',
        student_email TEXT DEFAULT '',
        address TEXT NOT NULL,
        neighborhood TEXT DEFAULT '',
        city TEXT NOT NULL,
        instrument TEXT NOT NULL,
        experience TEXT DEFAULT '',
        is_minor INTEGER NOT NULL DEFAULT 0,
        guardian_name TEXT DEFAULT '',
        guardian_cpf TEXT DEFAULT '',
        guardian_phone TEXT DEFAULT '',
        guardian_email TEXT DEFAULT '',
        guardian_relationship TEXT DEFAULT '',
        emergency_name TEXT DEFAULT '',
        emergency_phone TEXT DEFAULT '',
        image_authorization INTEGER NOT NULL DEFAULT 0,
        participation_authorization INTEGER NOT NULL DEFAULT 0,
        instrument_commitment INTEGER NOT NULL DEFAULT 0,
        uniform_commitment INTEGER NOT NULL DEFAULT 0,
        privacy_ack INTEGER NOT NULL DEFAULT 0,
        signer_name TEXT NOT NULL,
        signed_at TEXT NOT NULL,
        terms_version TEXT NOT NULL DEFAULT '2026.1',
        term_participation_snapshot TEXT NOT NULL DEFAULT '',
        term_image_snapshot TEXT NOT NULL DEFAULT '',
        term_instrument_snapshot TEXT NOT NULL DEFAULT '',
        term_uniform_snapshot TEXT NOT NULL DEFAULT '',
        term_privacy_snapshot TEXT NOT NULL DEFAULT '',
        admin_notes TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_enrollments_status ON enrollments(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_enrollments_instrument ON enrollments(instrument)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_enrollments_student_name ON enrollments(student_name)");

    // V2.3 — períodos de matrícula, número de matrícula, foto e exclusão controlada.
    db_add_column($pdo,'admins',"role TEXT NOT NULL DEFAULT 'ADMIN_GERAL'");
    // V2.8.0 — dados de recuperação do administrador.
    db_add_column($pdo,'admins',"birth_date TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'admins',"cpf TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"period_id INTEGER DEFAULT NULL");
    db_add_column($pdo,'enrollments',"registration_number TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"photo_path TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"deleted_at TEXT DEFAULT NULL");
    db_add_column($pdo,'enrollments',"deleted_by INTEGER DEFAULT NULL");

    $pdo->exec("CREATE TABLE IF NOT EXISTS enrollment_periods(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        closed_at TEXT DEFAULT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_periods_active ON enrollment_periods(active)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_enrollments_period ON enrollments(period_id)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollments_registration_number
        ON enrollments(registration_number) WHERE registration_number<>''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_cards(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrollment_id INTEGER NOT NULL UNIQUE,
        issued_at TEXT NOT NULL,
        valid_until TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'ATIVA',
        deactivated_at TEXT DEFAULT NULL,
        deactivation_reason TEXT DEFAULT '',
        issued_by INTEGER DEFAULT NULL,
        FOREIGN KEY(enrollment_id) REFERENCES enrollments(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_student_cards_status ON student_cards(status)");

    // Matrículas antigas continuam válidas e passam a usar o protocolo como número de matrícula.
    $pdo->exec("UPDATE enrollments SET registration_number=protocol
               WHERE registration_number IS NULL OR registration_number=''");


    // V2.4 — nome social/apelido autorizado, QR, presença, equipe e crachás.
    $pdo->exec("UPDATE settings SET setting_value='0' WHERE setting_key='team_registration_open'");
    db_add_column($pdo,'enrollments',"preferred_name TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"name_respect_ack INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'enrollments',"qr_token TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"term_name_respect_snapshot TEXT NOT NULL DEFAULT ''");
    // V2.4.4 — responsável obrigatório para menor + documentos do acompanhante.
    db_add_column($pdo,'enrollments',"guardian_birth_date TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"guardian_address TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"guardian_neighborhood TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"guardian_city TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"guardian_photo_path TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"guardian_qr_token TEXT NOT NULL DEFAULT ''");

    // V2.7.0 — situação escolar/profissional e declarações automáticas.
    db_add_column($pdo,'enrollments',"currently_studying INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'enrollments',"school_network TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"school_name TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"works_currently INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'enrollments',"employer_name TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'enrollments',"needs_work_declaration INTEGER NOT NULL DEFAULT 0");

    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollments_guardian_qr
        ON enrollments(guardian_qr_token) WHERE guardian_qr_token<>''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS guardian_cards(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        enrollment_id INTEGER NOT NULL UNIQUE,
        companion_number TEXT NOT NULL UNIQUE,
        issued_at TEXT NOT NULL,
        valid_until TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'ATIVA',
        deactivated_at TEXT DEFAULT NULL,
        deactivation_reason TEXT DEFAULT '',
        issued_by INTEGER DEFAULT NULL,
        FOREIGN KEY(enrollment_id) REFERENCES enrollments(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_guardian_cards_status ON guardian_cards(status)");

    // Avisos persistentes: cada administrador confirma individualmente a leitura.
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notifications(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL DEFAULT 'INFO',
        enrollment_id INTEGER DEFAULT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        FOREIGN KEY(enrollment_id) REFERENCES enrollments(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_notifications_type_id
        ON admin_notifications(type,id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_notification_reads(
        notification_id INTEGER NOT NULL,
        admin_id INTEGER NOT NULL,
        read_at TEXT NOT NULL,
        PRIMARY KEY(notification_id,admin_id),
        FOREIGN KEY(notification_id) REFERENCES admin_notifications(id) ON DELETE CASCADE,
        FOREIGN KEY(admin_id) REFERENCES admins(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_notification_reads_admin
        ON admin_notification_reads(admin_id,notification_id)");


    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_enrollments_qr_token
        ON enrollments(qr_token) WHERE qr_token<>''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS team_roles(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL DEFAULT 'EQUIPE',
        active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 100,
        created_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS team_members(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        application_number TEXT NOT NULL UNIQUE,
        qr_token TEXT NOT NULL UNIQUE,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'PENDENTE',
        role_id INTEGER DEFAULT NULL,
        full_name TEXT NOT NULL,
        preferred_name TEXT NOT NULL DEFAULT '',
        name_respect_ack INTEGER NOT NULL DEFAULT 0,
        birth_date TEXT NOT NULL,
        cpf TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        address TEXT NOT NULL DEFAULT '',
        city TEXT NOT NULL DEFAULT '',
        experience TEXT NOT NULL DEFAULT '',
        photo_path TEXT NOT NULL DEFAULT '',
        is_minor INTEGER NOT NULL DEFAULT 0,
        guardian_name TEXT NOT NULL DEFAULT '',
        guardian_phone TEXT NOT NULL DEFAULT '',
        guardian_relationship TEXT NOT NULL DEFAULT '',
        emergency_name TEXT NOT NULL DEFAULT '',
        emergency_phone TEXT NOT NULL DEFAULT '',
        image_authorization INTEGER NOT NULL DEFAULT 0,
        commitment_ack INTEGER NOT NULL DEFAULT 0,
        privacy_ack INTEGER NOT NULL DEFAULT 0,
        signer_name TEXT NOT NULL DEFAULT '',
        signed_at TEXT NOT NULL DEFAULT '',
        terms_version TEXT NOT NULL DEFAULT '2026.1',
        term_commitment_snapshot TEXT NOT NULL DEFAULT '',
        term_image_snapshot TEXT NOT NULL DEFAULT '',
        term_privacy_snapshot TEXT NOT NULL DEFAULT '',
        term_name_respect_snapshot TEXT NOT NULL DEFAULT '',
        admin_notes TEXT NOT NULL DEFAULT '',
        approved_at TEXT DEFAULT NULL,
        badge_status TEXT NOT NULL DEFAULT 'INATIVO',
        badge_issued_at TEXT DEFAULT NULL,
        badge_valid_until TEXT DEFAULT NULL,
        deleted_at TEXT DEFAULT NULL,
        FOREIGN KEY(role_id) REFERENCES team_roles(id)
    )");
    db_add_column($pdo,'team_members',"public_token TEXT NOT NULL DEFAULT ''");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_team_members_public_token ON team_members(public_token) WHERE public_token<>''");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_team_members_status ON team_members(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_team_members_role ON team_members(role_id)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_events(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        event_date TEXT NOT NULL,
        start_time TEXT NOT NULL DEFAULT '',
        end_time TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 0,
        notes TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        created_by INTEGER DEFAULT NULL,
        closed_at TEXT DEFAULT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_records(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL,
        person_type TEXT NOT NULL,
        person_id INTEGER NOT NULL,
        scanned_at TEXT NOT NULL,
        scan_method TEXT NOT NULL DEFAULT 'QR',
        UNIQUE(event_id,person_type,person_id),
        FOREIGN KEY(event_id) REFERENCES attendance_events(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_event ON attendance_records(event_id)");

    // V2.6.0 — instrutores/auxiliares internos e presença por ala em ENTRADA + SAÍDA.
    $pdo->exec("CREATE TABLE IF NOT EXISTS instructors(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        preferred_name TEXT NOT NULL DEFAULT '',
        role TEXT NOT NULL DEFAULT 'INSTRUTOR',
        birth_date TEXT NOT NULL DEFAULT '',
        cpf TEXT NOT NULL DEFAULT '',
        phone TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL DEFAULT '',
        photo_path TEXT NOT NULL DEFAULT '',
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        created_by INTEGER DEFAULT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_instructors_active ON instructors(active)");
    // V3.4.0 — QR permanente de instrutores/auxiliares para controle de entrada e saída.
    db_add_column($pdo,'instructors',"qr_token TEXT NOT NULL DEFAULT ''");
    $missingInstructorQr=$pdo->query("SELECT id FROM instructors WHERE qr_token IS NULL OR qr_token='' ORDER BY id")->fetchAll();
    $upInstructorQr=$pdo->prepare("UPDATE instructors SET qr_token=? WHERE id=?");
    foreach($missingInstructorQr as $iqr) $upInstructorQr->execute([bin2hex(random_bytes(16)),(int)$iqr['id']]);
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_instructors_qr_token ON instructors(qr_token) WHERE qr_token<>''");


    $pdo->exec("CREATE TABLE IF NOT EXISTS password_recovery_attempts(
        recovery_key TEXT PRIMARY KEY,
        attempts INTEGER NOT NULL DEFAULT 0,
        locked_until TEXT DEFAULT NULL,
        updated_at TEXT NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS instructor_areas(
        instructor_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        PRIMARY KEY(instructor_id,area),
        FOREIGN KEY(instructor_id) REFERENCES instructors(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_instructor_areas_area ON instructor_areas(area)");

    // V3.2.0 — código único de instrutor, acesso Master e avaliações musicais por ala.
    db_add_column($pdo,'instructors',"instructor_code TEXT NOT NULL DEFAULT ''");
    $blankCodes=$pdo->query("SELECT id FROM instructors WHERE instructor_code IS NULL OR instructor_code='' ORDER BY id")->fetchAll();
    $upCode=$pdo->prepare("UPDATE instructors SET instructor_code=? WHERE id=?");
    foreach($blankCodes as $bc){
        $iid=(int)$bc['id'];
        $upCode->execute(['BAMAB-I-'.str_pad((string)$iid,6,'0',STR_PAD_LEFT),$iid]);
    }
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_instructors_code ON instructors(instructor_code) WHERE instructor_code<>''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS instructor_master_access_logs(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER NOT NULL,
        instructor_id INTEGER NOT NULL,
        identifier_used TEXT NOT NULL DEFAULT '',
        client_ip TEXT NOT NULL DEFAULT '',
        accessed_at TEXT NOT NULL,
        FOREIGN KEY(admin_id) REFERENCES admins(id),
        FOREIGN KEY(instructor_id) REFERENCES instructors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_master_access_date ON instructor_master_access_logs(accessed_at)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_tests(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        test_date TEXT NOT NULL,
        evaluation_year INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'ABERTO',
        notes TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        created_by INTEGER DEFAULT NULL,
        FOREIGN KEY(created_by) REFERENCES admins(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_evaluation_tests_date ON evaluation_tests(test_date,status)");
    // V3.3.0 — cada instrutor pode iniciar sua própria avaliação e enviar somente o relatório final ao Admin.
    db_add_column($pdo,'evaluation_tests',"owner_instructor_id INTEGER DEFAULT NULL");
    db_add_column($pdo,'evaluation_tests',"started_at TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'evaluation_tests',"report_sent INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'evaluation_tests',"submitted_to_admin_at TEXT NOT NULL DEFAULT ''");
    db_add_column($pdo,'evaluation_tests',"source TEXT NOT NULL DEFAULT 'LEGADO'");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_evaluation_tests_owner ON evaluation_tests(owner_instructor_id,status,report_sent,test_date)");
    // Testes antigos encerrados já representam relatórios finalizados.
    $pdo->exec("UPDATE evaluation_tests SET report_sent=1,submitted_to_admin_at=CASE WHEN submitted_to_admin_at='' THEN updated_at ELSE submitted_to_admin_at END WHERE owner_instructor_id IS NULL AND status='ENCERRADO' AND report_sent=0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_test_areas(
        test_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        PRIMARY KEY(test_id,area),
        FOREIGN KEY(test_id) REFERENCES evaluation_tests(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_criteria(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        test_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        position INTEGER NOT NULL,
        criterion TEXT NOT NULL,
        UNIQUE(test_id,area,position),
        FOREIGN KEY(test_id) REFERENCES evaluation_tests(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_evaluation_criteria_area ON evaluation_criteria(test_id,area,position)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_submissions(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        test_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        student_id INTEGER NOT NULL,
        instructor_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'CONCLUIDA',
        final_score REAL NOT NULL DEFAULT 0,
        completed_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(test_id,area,student_id,instructor_id),
        FOREIGN KEY(test_id) REFERENCES evaluation_tests(id) ON DELETE CASCADE,
        FOREIGN KEY(student_id) REFERENCES enrollments(id),
        FOREIGN KEY(instructor_id) REFERENCES instructors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_evaluation_submissions_test ON evaluation_submissions(test_id,area,status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_evaluation_submissions_instructor ON evaluation_submissions(instructor_id,test_id)");
    db_add_column($pdo,'evaluation_submissions',"total_points INTEGER NOT NULL DEFAULT 0");
    $pdo->exec("UPDATE evaluation_submissions SET total_points=CAST(ROUND(final_score*10) AS INTEGER) WHERE total_points=0 AND final_score>0");

    $pdo->exec("CREATE TABLE IF NOT EXISTS evaluation_scores(
        submission_id INTEGER NOT NULL,
        criterion_id INTEGER NOT NULL,
        rating TEXT NOT NULL,
        score INTEGER NOT NULL,
        PRIMARY KEY(submission_id,criterion_id),
        FOREIGN KEY(submission_id) REFERENCES evaluation_submissions(id) ON DELETE CASCADE,
        FOREIGN KEY(criterion_id) REFERENCES evaluation_criteria(id) ON DELETE CASCADE
    )");

    db_add_column($pdo,'attendance_events',"event_type TEXT NOT NULL DEFAULT 'ENSAIO'");
    db_add_column($pdo,'attendance_events',"control_mode TEXT NOT NULL DEFAULT 'LEGACY'");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_event_areas(
        event_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        PRIMARY KEY(event_id,area),
        FOREIGN KEY(event_id) REFERENCES attendance_events(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_area_controls(
        event_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        phase TEXT NOT NULL DEFAULT 'FECHADO',
        entry_opened_at TEXT DEFAULT NULL,
        entry_closed_at TEXT DEFAULT NULL,
        exit_opened_at TEXT DEFAULT NULL,
        exit_closed_at TEXT DEFAULT NULL,
        last_instructor_id INTEGER DEFAULT NULL,
        updated_at TEXT NOT NULL,
        PRIMARY KEY(event_id,area),
        FOREIGN KEY(event_id) REFERENCES attendance_events(id) ON DELETE CASCADE,
        FOREIGN KEY(last_instructor_id) REFERENCES instructors(id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_checks(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL,
        person_type TEXT NOT NULL DEFAULT 'ALUNO',
        person_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        check_type TEXT NOT NULL,
        scanned_at TEXT NOT NULL,
        scan_method TEXT NOT NULL DEFAULT 'QR',
        instructor_id INTEGER DEFAULT NULL,
        UNIQUE(event_id,person_type,person_id,check_type),
        FOREIGN KEY(event_id) REFERENCES attendance_events(id) ON DELETE CASCADE,
        FOREIGN KEY(instructor_id) REFERENCES instructors(id)
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_checks_event_area ON attendance_checks(event_id,area)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_checks_person ON attendance_checks(person_type,person_id)");
    // V3.4.0 — identificação de quem realizou a leitura e controle geral do evento.
    db_add_column($pdo,'attendance_checks',"admin_id INTEGER DEFAULT NULL");
    db_add_column($pdo,'attendance_events',"include_students INTEGER NOT NULL DEFAULT 1");
    db_add_column($pdo,'attendance_events',"include_instructors INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'attendance_events',"include_team INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'attendance_events',"include_companions INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'attendance_events',"roster_frozen_at TEXT NOT NULL DEFAULT ''");

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_general_controls(
        event_id INTEGER PRIMARY KEY,
        phase TEXT NOT NULL DEFAULT 'FECHADO',
        entry_opened_at TEXT DEFAULT NULL,
        entry_closed_at TEXT DEFAULT NULL,
        exit_opened_at TEXT DEFAULT NULL,
        exit_closed_at TEXT DEFAULT NULL,
        last_admin_id INTEGER DEFAULT NULL,
        updated_at TEXT NOT NULL,
        FOREIGN KEY(event_id) REFERENCES attendance_events(id) ON DELETE CASCADE,
        FOREIGN KEY(last_admin_id) REFERENCES admins(id)
    )");

    // Lista esperada congelada no momento em que a entrada é aberta.
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_roster(
        event_id INTEGER NOT NULL,
        person_type TEXT NOT NULL,
        person_id INTEGER NOT NULL,
        name_snapshot TEXT NOT NULL DEFAULT '',
        preferred_snapshot TEXT NOT NULL DEFAULT '',
        number_snapshot TEXT NOT NULL DEFAULT '',
        role_snapshot TEXT NOT NULL DEFAULT '',
        area_snapshot TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL,
        PRIMARY KEY(event_id,person_type,person_id),
        FOREIGN KEY(event_id) REFERENCES attendance_events(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_roster_event ON attendance_roster(event_id,person_type)");


    // V2.6.1 — programação semanal. A data real nasce quando o instrutor acessa sua área.
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_schedules(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        event_type TEXT NOT NULL DEFAULT 'ENSAIO',
        weekday INTEGER NOT NULL,
        start_time TEXT NOT NULL DEFAULT '',
        end_time TEXT NOT NULL DEFAULT '',
        notes TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        created_by INTEGER DEFAULT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_schedules_weekday ON attendance_schedules(weekday,active)");
    db_add_column($pdo,'attendance_schedules',"include_students INTEGER NOT NULL DEFAULT 1");
    db_add_column($pdo,'attendance_schedules',"include_instructors INTEGER NOT NULL DEFAULT 1");
    db_add_column($pdo,'attendance_schedules',"include_team INTEGER NOT NULL DEFAULT 0");
    db_add_column($pdo,'attendance_schedules',"include_companions INTEGER NOT NULL DEFAULT 0");


    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_schedule_areas(
        schedule_id INTEGER NOT NULL,
        area TEXT NOT NULL,
        PRIMARY KEY(schedule_id,area),
        FOREIGN KEY(schedule_id) REFERENCES attendance_schedules(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attendance_schedule_areas_area ON attendance_schedule_areas(area)");

    db_add_column($pdo,'attendance_events',"schedule_id INTEGER DEFAULT NULL");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_attendance_events_schedule_date
        ON attendance_events(schedule_id,event_date) WHERE schedule_id IS NOT NULL");



    // Papéis iniciais — todos editáveis pelo Admin Geral.
    $defaultRoles=[
        ['COORDENAÇÃO','COORDENAÇÃO',10],
        ['COREÓGRAFO','COREOGRAFIA',20],
        ['MÓ','EQUIPE',30],
        ['BALIZA','EQUIPE',40],
        ['MÍDIA','MÍDIA',50],
        ['SECRETÁRIO','ADMINISTRATIVO',60],
        ['PRODUÇÃO','PRODUÇÃO',70],
        ['COORDENADOR','COORDENAÇÃO',80],
        ['COORDENADOR GERAL','COORDENAÇÃO',90],
    ];
    $roleInsert=$pdo->prepare("INSERT OR IGNORE INTO team_roles(name,category,active,sort_order,created_at) VALUES(?,?,1,?,?)");
    foreach($defaultRoles as $dr) $roleInsert->execute([$dr[0],$dr[1],$dr[2],now_iso()]);

    // Gera QR para matrículas antigas que ainda não possuem token.
    $missingQr=$pdo->query("SELECT id FROM enrollments WHERE qr_token IS NULL OR qr_token=''")->fetchAll();
    $qrUpdate=$pdo->prepare("UPDATE enrollments SET qr_token=? WHERE id=?");
    foreach($missingQr as $mq) $qrUpdate->execute([bin2hex(random_bytes(16)),(int)$mq['id']]);

    $defaults=[
        'site_name'=>'BAMAB',
        'site_subtitle'=>'BANDA AMARAL BRASIL',
        'hero_title'=>'BAMAB',
        'hero_text'=>'A BAMAB tem como missão promover a cultura musical, a disciplina, o trabalho em equipe e o orgulho de representar nossa cidade. Mais que música, formamos cidadãos e escrevemos histórias de superação, dedicação e conquistas.',
        'history_title'=>'Como a Banda Começou',
        'history_text'=>'Conte aqui como a banda começou, quem participou da fundação, os primeiros ensaios, apresentações e os momentos que marcaram sua trajetória.',
        'instagram_url'=>'https://www.instagram.com/bamab_slp/',
        'youtube_url'=>'',
        'facebook_url'=>'',
        'whatsapp_url'=>'',
        'contact_email'=>'',
        'address'=>'',
        'report_city'=>'Santa Luzia do Paruá',
        'report_state'=>'Maranhão',
        'logo_path'=>'assets/brasao_bamab_2026.png',
        'hero_image_path'=>'assets/hero_banda_inicial.png',
        'primary_color'=>'#090909',
        'secondary_color'=>'#c99a34',
        'dark_color'=>'#050505',
        'light_color'=>'#ededed',
        'welcome_title'=>'SEJAM BEM-VINDOS!',
        'announcement_heading'=>'ESPAÇO EDITÁVEL DO ADMIN',
        'announcement_intro'=>'Use este espaço para comunicados importantes, avisos, eventos, convocações, mudanças de horário e muito mais.',
        'announcement_title'=>'COMUNICADO IMPORTANTE',
        'announcement_text'=>'Cadastre aqui no painel administrativo o próximo ensaio, apresentação, campeonato ou aviso importante da BAMAB.',
        'motto'=>'DISCIPLINA NA ROTINA, HARMONIA NA ALMA, EXCELÊNCIA NA APRESENTAÇÃO.',
        'enrollment_open'=>'1',
        'enrollment_title'=>'MATRÍCULAS BAMAB',
        'enrollment_intro'=>'Faça sua inscrição para participar da Banda Amaral Brasil. Para menores de 18 anos, o responsável legal deve preencher e concluir a matrícula.',
        'enrollment_terms_version'=>'2026.1',
        'card_validity_months'=>'12',
        'team_registration_open'=>'0',
        'team_registration_title'=>'INSCRIÇÃO — EQUIPE BAMAB',
        'team_registration_intro'=>'Ficha destinada à Coordenação, Produção, Mídia e demais funções de apoio da BAMAB.',
        'team_terms_version'=>'2026.1',
        'term_name_respect'=>'O nome completo, nome social ou apelido informado pelo próprio inscrito/responsável é a forma autorizada de tratamento. É proibido atribuir, impor ou utilizar apelidos que a pessoa não tenha escolhido ou que lhe causem constrangimento ou desconforto. Se nenhum apelido/nome social for informado, deve-se utilizar o nome da pessoa.',
        'team_term_commitment'=>'Declaro compromisso com as normas internas, horários, ensaios, apresentações, responsabilidades da função e orientações da Coordenação Geral da BAMAB.',
        'team_term_image'=>'A autorização de imagem e voz é uma escolha separada. A recusa não impede, por si só, a análise da inscrição para a equipe.',
        'team_term_privacy'=>'Os dados desta ficha serão utilizados para cadastro, identificação, comunicação, organização interna, crachá, controle de presença e atividades administrativas da BAMAB.',
        'student_badge_validity_months'=>'12',
        'team_badge_validity_months'=>'12',
        'enrollment_thanks_title'=>'OBRIGADO POR FAZER PARTE DA BAMAB!',
        'enrollment_thanks_text'=>'A BAMAB agradece sua inscrição e confiança. Seja bem-vindo(a) à nossa família musical. Desejamos uma jornada de disciplina, aprendizado, harmonia e grandes conquistas.',
        'card_back_notice'=>'Documento pessoal e intransferível. Apresentar nos ensaios e eventos oficiais. Em caso de cancelamento da matrícula, a carteirinha será desativada automaticamente.',
        'badge_back_rules'=>'Crachá de identificação oficial da Banda Amaral Brasil. Uso pessoal e intransferível. Apresentar quando solicitado pela Coordenação. Em caso de desligamento ou cancelamento, o crachá deverá ser considerado inativo.',
        // Editor visual dos documentos
        'doc_primary_color'=>'#0b1118',
        'doc_gold_color'=>'#d1a33f',
        'doc_wine_color'=>'#7d1020',
        'doc_light_color'=>'#f5f1e9',
        'doc_text_color'=>'#171717',
        'doc_student_card_title'=>'CARTEIRINHA DO ALUNO',
        'doc_student_badge_title'=>'CRACHÁ OFICIAL BAMAB',
        'doc_team_card_title'=>'CARTEIRINHA DA EQUIPE',
        'doc_team_badge_title'=>'CRACHÁ OFICIAL BAMAB — EQUIPE',
        'doc_companion_card_title'=>'CARTEIRINHA DO ACOMPANHANTE',
        'doc_companion_badge_title'=>'CRACHÁ DE ACOMPANHANTE',
        'doc_companion_number_label'=>'Nº ACOMPANHANTE',
        'doc_companion_role_label'=>'VÍNCULO / RESPONSÁVEL',

        'doc_student_name_label'=>'NOME DO ALUNO',
        'doc_preferred_name_label'=>'NOME SOCIAL / APELIDO',
        'doc_student_card_name_mode'=>'first_last_surname',
        'doc_team_card_name_mode'=>'first_last_surname',
        'doc_number_label'=>'MATRÍCULA',
        'doc_role_label'=>'INSTRUMENTO / ALA',
        'doc_team_number_label'=>'NÚMERO DA EQUIPE',
        'doc_team_role_label'=>'FUNÇÃO',
        'doc_validity_label'=>'VALIDADE',
        'doc_qr_label'=>'PRESENÇA / VERIFICAÇÃO',
        'doc_motto'=>'DISCIPLINA • RESPEITO • UNIÃO • EXCELÊNCIA',
        'doc_card_title_size'=>'4.2',
        'doc_card_name_size'=>'2.8',
        'doc_card_field_size'=>'2.2',
        'doc_card_label_size'=>'1.65',
        'doc_badge_title_size'=>'3.8',
        'doc_badge_name_size'=>'4.7',
        'doc_badge_field_size'=>'2.45',
        'doc_badge_label_size'=>'2.0',
        'doc_back_title_size'=>'4.0',
        'doc_back_text_size'=>'1.85',
        'doc_show_preferred_name'=>'1',
        'doc_show_qr_on_front'=>'1',
        'doc_show_qr_on_back'=>'1',
        'doc_show_motto'=>'1',
        'team_card_back_notice'=>'Documento institucional e intransferível. Apresentar em ensaios, eventos e atividades oficiais. Em caso de desligamento ou cancelamento, a identificação será desativada.',
        'companion_card_back_notice'=>'Documento vinculado à matrícula do aluno menor. Uso pessoal do responsável/acompanhante cadastrado. Em caso de cancelamento ou desativação da matrícula do menor, este documento também é desativado automaticamente.',



        'term_participation'=>'AUTORIZAÇÃO DE PARTICIPAÇÃO: Declaro que as informações desta ficha são verdadeiras e autorizo a participação do inscrito nas atividades da BAMAB, incluindo ensaios, apresentações, desfiles, campeonatos, eventos e demais atividades previamente comunicadas pela Coordenação. Para participante menor de 18 anos, esta autorização é prestada pelo responsável legal identificado nesta matrícula.',
        'term_image'=>'AUTORIZAÇÃO DE USO DE IMAGEM E VOZ: Autorizo, de forma gratuita, o registro e o uso institucional de imagem e voz do participante em fotos e vídeos realizados em atividades da BAMAB, para divulgação no site, redes sociais, materiais informativos e memória histórica da Banda. A opção de NÃO autorizar imagem não impede a matrícula ou a participação nas atividades. A autorização poderá ser revista para usos futuros mediante solicitação à Coordenação, observadas as obrigações legais e os materiais já legitimamente produzidos.',
        'term_instrument'=>'RESPONSABILIDADE POR INSTRUMENTOS E PATRIMÔNIO: O participante e, quando menor de idade, seu responsável legal comprometem-se a zelar pelos instrumentos, acessórios, uniformes e demais bens eventualmente cedidos para uso, utilizando-os apenas nas atividades autorizadas, guardando-os adequadamente, não os cedendo a terceiros, comunicando imediatamente avarias, perda ou extravio e devolvendo-os quando solicitado. Eventual responsabilidade por dano ou perda será analisada caso a caso pela Coordenação, com ciência do participante ou responsável, sem cobrança automática.',
        'term_uniform'=>'COMPROMISSO COM UNIFORME OFICIAL, CAMISAS E PEÇAS EXTERNAS: Declaro estar ciente de que uniforme oficial, camisas, acessórios ou outras peças poderão ser confeccionados ou adquiridos junto a fornecedores externos. Quando houver custo, valor, prazo, fornecedor e forma de pagamento deverão ser informados previamente. Havendo aceite da aquisição, o pagamento será de responsabilidade do participante ou de seu responsável legal. Este termo não autoriza cobranças não informadas previamente.',
        'term_privacy'=>'PRIVACIDADE E DADOS PESSOAIS: Os dados desta matrícula serão utilizados para cadastro, contato, organização das atividades, gestão de participação, controle administrativo de instrumentos e uniformes e demais finalidades necessárias ao funcionamento da BAMAB. O acesso deve ficar restrito à Coordenação autorizada. Dados de crianças e adolescentes devem ser tratados priorizando seu melhor interesse. O titular ou responsável poderá solicitar atualização e esclarecimentos sobre o tratamento dos dados junto à Coordenação.',
        'sponsor_widget_enabled'=>'1',
        'sponsor_widget_title'=>'PARCEIROS DA BAMAB',
        'sponsor_widget_subtitle'=>'PATROCINADORES • APOIADORES • IDEALIZADORES',
        'sponsor_widget_show_name'=>'1',
        'institutional_widget_enabled'=>'1',
        'institutional_widget_title'=>'APOIO INSTITUCIONAL',
        'institutional_widget_subtitle'=>'PREFEITURA • SECRETARIAS • ÓRGÃOS PARCEIROS',
        'institutional_widget_show_name'=>'1',
        'institutional_widget_position'=>'left',
        'institutional_widget_width'=>'248',
        // Contatos dinâmicos
        'contact_channels_seeded'=>'0',
        'contact_section_title'=>'FALE COM A BAMAB',
        'contact_section_subtitle'=>'Canais oficiais e secundários de atendimento, informações e convites.',
        'contact_whatsapp_button_text'=>'CHAMAR NO WHATSAPP',
        'home_contact_enabled'=>'1',
        'home_contact_title'=>'CONTATOS OFICIAIS',
        // Certificados e diplomas
        'recognition_certificate_title'=>'CERTIFICADO DE RECONHECIMENTO',
        'recognition_certificate_subtitle'=>'PARTICIPAÇÃO, EVOLUÇÃO E DEDICAÇÃO — {ANO}',
        'recognition_certificate_body_student'=>'A Banda Amaral Brasil — BAMAB confere a {NOME} este Certificado de Reconhecimento pela presença marcante, dedicação, disciplina e evolução demonstradas ao longo de {ANO}. Cada ensaio, cada aprendizado e cada passo dado em conjunto contribuíram para fortalecer a música, a união e a história desta Banda.',
        'recognition_certificate_dedication_student'=>'Que este reconhecimento guarde a memória de um ano de conquistas e seja também um convite para continuar sonhando, aprendendo e fazendo da música um caminho de coragem, amizade e excelência.',
        'recognition_certificate_body_team'=>'A Banda Amaral Brasil — BAMAB confere a {NOME} este Certificado de Reconhecimento pela contribuição responsável, dedicação e compromisso com a equipe durante {ANO}, reconhecendo o valor de quem transforma trabalho, cuidado e presença em força para toda a Banda.',
        'recognition_certificate_dedication_team'=>'Nossa gratidão a quem serve com compromisso e ajuda a construir, nos bastidores e à frente de cada atividade, uma BAMAB mais forte, organizada e inspiradora.',
        'recognition_diploma_title'=>'DIPLOMA DE RECONHECIMENTO',
        'recognition_diploma_subtitle'=>'MÉRITO, TRAJETÓRIA E EXCELÊNCIA — {ANO}',
        'recognition_diploma_body_student'=>'A Banda Amaral Brasil — BAMAB outorga a {NOME} este Diploma de Reconhecimento em homenagem à trajetória construída com constância, disciplina, compromisso e amor pela música. Em {ANO}, sua caminhada representa não apenas desenvolvimento individual, mas também contribuição verdadeira para a identidade, a memória e a excelência artística da Banda.',
        'recognition_diploma_dedication_student'=>'Que cada conquista registrada nesta trajetória recorde que a música é feita de perseverança, escuta, união e propósito. Receba este Diploma como símbolo de respeito por tudo o que já foi construído e de confiança em tudo o que ainda poderá ser alcançado.',
        'recognition_diploma_body_team'=>'A Banda Amaral Brasil — BAMAB outorga a {NOME} este Diploma de Reconhecimento pela trajetória de serviço, liderança, dedicação e contribuição contínua ao desenvolvimento da equipe e das atividades da Banda durante {ANO}.',
        'recognition_diploma_dedication_team'=>'Este Diploma expressa a gratidão da BAMAB a quem oferece tempo, conhecimento e responsabilidade para que cada integrante encontre espaço para aprender, evoluir e representar a Banda com dignidade.',
        'recognition_partner_certificate_title'=>'CERTIFICADO DE PARCERIA E RECONHECIMENTO',
        'recognition_partner_certificate_subtitle'=>'GRATIDÃO, APOIO E COMPROMISSO COM A CULTURA — {ANO}',
        'recognition_certificate_body_partner'=>'A Banda Amaral Brasil — BAMAB concede a {NOME} este Certificado de Parceria e Reconhecimento, em agradecimento pelo apoio, confiança e contribuição destinados ao fortalecimento de nossas atividades culturais, musicais e formativas durante {ANO}.',
        'recognition_certificate_dedication_partner'=>'Parcerias verdadeiras ajudam sonhos coletivos a permanecer de pé. Receba nossa gratidão por acreditar na cultura, na juventude, na música e no trabalho que a BAMAB realiza em favor da comunidade.',
        'recognition_certificate_body_institutional'=>'A Banda Amaral Brasil — BAMAB concede a {NOME} este Certificado de Reconhecimento Institucional, registrando sua valiosa colaboração e apoio às ações culturais, musicais e educativas realizadas durante {ANO}.',
        'recognition_certificate_dedication_institutional'=>'Nosso reconhecimento a toda instituição que compreende a cultura como patrimônio vivo e contribui para que a música continue formando pessoas, fortalecendo vínculos e inspirando novas gerações.',
        'recognition_history_title'=>'HISTÓRICO ESCOLAR E DESENVOLVIMENTO MUSICAL',
        'recognition_history_subtitle'=>'REGISTRO DE AVALIAÇÕES E EVOLUÇÃO — {ANO}',
        'recognition_history_note'=>'Este histórico apresenta somente as notas finais das avaliações concluídas e enviadas oficialmente ao sistema no ano indicado. Os critérios detalhados permanecem registrados na área dos instrutores responsáveis.',
        'recognition_footer_text'=>'BANDA AMARAL BRASIL — Cultura, disciplina, união e excelência.',
        'recognition_place_text'=>'Santa Luzia do Paruá — Maranhão',
        'recognition_show_serial'=>'1',
        'footer_text'=>'Orgulho de nossa cidade. Inspiração de gerações.',
    ];
    $st=$pdo->prepare("INSERT OR IGNORE INTO settings(setting_key,setting_value) VALUES(?,?)");
    foreach($defaults as $k=>$v) $st->execute([$k,$v]);

    // Importa os contatos antigos uma única vez para o novo gerenciador dinâmico.
    $seedFlag=$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key='contact_channels_seeded'");$seedFlag->execute();
    if((string)($seedFlag->fetchColumn()?:'0')!=='1'){
        $legacy=[];foreach(['instagram_url','whatsapp_url','contact_email'] as $lk){$q=$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?");$q->execute([$lk]);$legacy[$lk]=(string)($q->fetchColumn()?:'');}
        $ins=$pdo->prepare("INSERT INTO contact_channels(label,contact_type,contact_value,link_url,whatsapp_message,channel_level,description,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?,'OFICIAL',?,?,1,?,?)");
        $now=now_iso();$order=10;
        if(trim($legacy['instagram_url'])!==''){$ins->execute(['Instagram Oficial','INSTAGRAM','Instagram BAMAB',$legacy['instagram_url'],'','Canal oficial da BAMAB no Instagram.',$order,$now,$now]);$order+=10;}
        if(trim($legacy['whatsapp_url'])!==''){$ins->execute(['WhatsApp Oficial','WHATSAPP','WhatsApp BAMAB',$legacy['whatsapp_url'],'Olá! Gostaria de falar com a BAMAB.','Atendimento oficial por WhatsApp.',$order,$now,$now]);$order+=10;}
        if(trim($legacy['contact_email'])!==''){$ins->execute(['E-mail Oficial','EMAIL',$legacy['contact_email'],'','','E-mail oficial da BAMAB.',$order,$now,$now]);}
        $pdo->prepare("UPDATE settings SET setting_value='1' WHERE setting_key='contact_channels_seeded'")->execute();
    }
}

function setting(string $key,string $default=''): string {
    $st=db()->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $st->execute([$key]);
    $v=$st->fetchColumn();
    return $v===false?$default:(string)$v;
}
function set_setting(string $key,string $value): void {
    db()->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)
      ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value")->execute([$key,$value]);
}
function e(?string $s): string { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function safe_external_url(?string $url): string {
    $url=trim((string)$url);
    if($url==='') return '';
    if(!filter_var($url,FILTER_VALIDATE_URL)) return '';
    $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
    $host=(string)parse_url($url,PHP_URL_HOST);
    if(!in_array($scheme,['http','https'],true) || $host==='') return '';
    return $url;
}
function valid_hex_color(?string $value,string $fallback='#000000'): string {
    $value=trim((string)$value);
    return preg_match('/^#[0-9a-fA-F]{6}$/',$value) ? strtolower($value) : strtolower($fallback);
}
function app_asset(string $path): string {
    return $path.(str_contains($path,'?')?'&':'?').'v='.rawurlencode(APP_VERSION);
}
function now_iso(): string { return date('Y-m-d H:i:s'); }
function bamab_greeting(): string {
    $h=(int)date('G');
    if($h>=0 && $h<5) return 'Boa madrugada';
    if($h>=5 && $h<12) return 'Bom dia';
    if($h>=12 && $h<18) return 'Boa tarde';
    return 'Boa noite';
}
function bamab_music_phrases(): array {
    return [
        'A música transforma disciplina em expressão.',
        'Cada ensaio é um passo para fazer a arte soar mais longe.',
        'Quem vive a música aprende a ouvir, criar e crescer.',
        'A excelência nasce quando talento encontra constância.',
        'Uma banda forte se constrói nota por nota, juntos.',
        'A arte ganha vida quando cada músico oferece o seu melhor.',
        'Estudar música é transformar dedicação em emoção.',
        'Cada nota bem cuidada fortalece a história que a banda conta.'
    ];
}
function bamab_music_phrase(): string {
    $a=bamab_music_phrases();
    $idx=((int)date('z')+(int)date('G'))%count($a);
    return $a[$idx];
}
function recovery_normalize_name(string $name): string {
    $name=trim(preg_replace('/\s+/u',' ',$name) ?? $name);
    return mb_strtolower($name,'UTF-8');
}
function recovery_client_key(string $scope): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'local');
    return hash('sha256','BAMAB|'.$scope.'|'.$ip);
}
function recovery_locked(string $scope): bool {
    $st=db()->prepare("SELECT locked_until FROM password_recovery_attempts WHERE recovery_key=?");
    $st->execute([recovery_client_key($scope)]);
    $locked=(string)($st->fetchColumn()?:'');
    return $locked!=='' && strtotime($locked)>time();
}
function recovery_register_failure(string $scope): void {
    $pdo=db();$key=recovery_client_key($scope);
    $st=$pdo->prepare("SELECT * FROM password_recovery_attempts WHERE recovery_key=?");$st->execute([$key]);$r=$st->fetch();
    $attempts=1;
    if($r){
        $updated=strtotime((string)$r['updated_at']);
        $attempts=($updated!==false && $updated>time()-1800)?((int)$r['attempts']+1):1;
    }
    $locked=$attempts>=5?date('Y-m-d H:i:s',strtotime('+15 minutes')):null;
    $pdo->prepare("INSERT INTO password_recovery_attempts(recovery_key,attempts,locked_until,updated_at)
        VALUES(?,?,?,?) ON CONFLICT(recovery_key) DO UPDATE SET attempts=excluded.attempts,locked_until=excluded.locked_until,updated_at=excluded.updated_at")
        ->execute([$key,$attempts,$locked,date('Y-m-d H:i:s')]);
}
function recovery_clear(string $scope): void {
    db()->prepare("DELETE FROM password_recovery_attempts WHERE recovery_key=?")->execute([recovery_client_key($scope)]);
}
function admin_recovery_ready(int $adminId): bool {
    $st=db()->prepare("SELECT display_name,birth_date,cpf FROM admins WHERE id=?");$st->execute([$adminId]);$r=$st->fetch();
    return $r && trim((string)$r['display_name'])!=='' && trim((string)$r['birth_date'])!=='' && cpf_valid((string)$r['cpf']);
}


function contact_channel_types(): array {
    return [
        'WHATSAPP'=>'WhatsApp','TELEFONE'=>'Telefone','EMAIL'=>'E-mail','INSTAGRAM'=>'Instagram',
        'FACEBOOK'=>'Facebook','YOUTUBE'=>'YouTube','SITE'=>'Site','OUTRO'=>'Outro'
    ];
}
function contact_channel_levels(): array { return ['OFICIAL'=>'Oficial','SECUNDARIO'=>'Secundário']; }
function normalize_phone_digits(string $value): string { return preg_replace('/\D+/','',$value) ?? ''; }
function contact_channel_link(array $c): string {
    $type=(string)($c['contact_type']??'');$value=trim((string)($c['contact_value']??''));$explicit=safe_external_url((string)($c['link_url']??''));
    if($type==='WHATSAPP'){
        if($explicit!=='') return $explicit;
        $digits=normalize_phone_digits($value);
        if(strlen($digits)===10||strlen($digits)===11)$digits='55'.$digits;
        if(strlen($digits)<12) return '';
        $msg=trim((string)($c['whatsapp_message']??''));
        return 'https://wa.me/'.$digits.($msg!==''?'?text='.rawurlencode($msg):'');
    }
    if($type==='TELEFONE'){
        $digits=normalize_phone_digits($value); if(strlen($digits)===10||strlen($digits)===11)$digits='55'.$digits; return $digits!==''?'tel:+'.$digits:'';
    }
    if($type==='EMAIL') return filter_var($value,FILTER_VALIDATE_EMAIL)?'mailto:'.$value:'';
    return $explicit;
}
function active_contact_channels(?string $level=null): array {
    $sql="SELECT * FROM contact_channels WHERE active=1";$params=[];
    if($level!==null){$sql.=" AND channel_level=?";$params[]=$level;}
    $sql.=" ORDER BY CASE channel_level WHEN 'OFICIAL' THEN 0 ELSE 1 END,sort_order,id";
    $st=db()->prepare($sql);$st->execute($params);return $st->fetchAll();
}
function first_whatsapp_channel(): ?array {
    $r=db()->query("SELECT * FROM contact_channels WHERE active=1 AND contact_type='WHATSAPP' ORDER BY CASE channel_level WHEN 'OFICIAL' THEN 0 ELSE 1 END,sort_order,id LIMIT 1")->fetch();
    return $r?:null;
}
function recognition_document_types(): array { return ['CERTIFICADO'=>'Certificado','DIPLOMA'=>'Diploma']; }
function recognition_person_types(): array { return ['ALUNO'=>'Aluno','EQUIPE'=>'Equipe','PARCEIRO'=>'Parceiro BAMAB','INSTITUCIONAL'=>'Apoio Institucional']; }
function recognition_document_types_for_person(string $type): array {
    return in_array($type,['PARCEIRO','INSTITUCIONAL'],true) ? ['CERTIFICADO'=>'Certificado'] : recognition_document_types();
}
function recognition_serial(int $year,string $type): string {
    $prefix=$type==='DIPLOMA'?'DIP':'CERT';$pdo=db();
    for($seq=1;$seq<100000;$seq++){
        $serial=sprintf('BAMAB-%d-%s-%04d',$year,$prefix,$seq);
        $st=$pdo->prepare("SELECT COUNT(*) FROM recognition_documents WHERE serial_number=?");$st->execute([$serial]);
        if((int)$st->fetchColumn()===0)return $serial;
    }
    return 'BAMAB-'.$year.'-'.$prefix.'-'.strtoupper(bin2hex(random_bytes(4)));
}
function recognition_person(string $type,int $id): ?array {
    if($type==='ALUNO'){
        $st=db()->prepare("SELECT id,student_name full_name,preferred_name,registration_number reference,instrument role_name,created_at,status,deleted_at,'' logo_path,'' description,'' website_url FROM enrollments WHERE id=?");$st->execute([$id]);$r=$st->fetch();
        if(!$r||!empty($r['deleted_at']))return null;return $r;
    }
    if($type==='EQUIPE'){
        $st=db()->prepare("SELECT tm.id,tm.full_name,tm.preferred_name,tm.application_number reference,tr.name role_name,tm.created_at,tm.status,tm.deleted_at,'' logo_path,'' description,'' website_url FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.id=?");$st->execute([$id]);$r=$st->fetch();
        if(!$r||!empty($r['deleted_at']))return null;return $r;
    }
    if($type==='PARCEIRO'){
        $st=db()->prepare("SELECT id,name full_name,'' preferred_name,category reference,category role_name,created_at,CASE active WHEN 1 THEN 'ATIVO' ELSE 'OCULTO' END status,'' deleted_at,logo_path,description,website_url FROM sponsors WHERE id=?");$st->execute([$id]);return $st->fetch()?:null;
    }
    if($type==='INSTITUCIONAL'){
        $st=db()->prepare("SELECT id,name full_name,'' preferred_name,category reference,category role_name,created_at,CASE active WHEN 1 THEN 'ATIVO' ELSE 'OCULTO' END status,'' deleted_at,logo_path,description,website_url FROM institutional_supporters WHERE id=?");$st->execute([$id]);return $st->fetch()?:null;
    }
    return null;
}
function recognition_display_name(array $p): string { return display_person_name((string)($p['full_name']??''),(string)($p['preferred_name']??'')); }
function recognition_suggestion(array $p,int $year,string $personType='ALUNO'): string {
    if(in_array($personType,['PARCEIRO','INSTITUCIONAL'],true))return 'CERTIFICADO DE PARCERIA / RECONHECIMENTO';
    $entered=(int)substr((string)($p['created_at']??''),0,4);
    return $entered===$year?'NOVATO DO ANO — CERTIFICADO SUGERIDO':'VETERANO — DIPLOMA SUGERIDO';
}
function recognition_issue(string $personType,int $personId,string $docType,int $year,int $adminId,string $notes=''): int {
    if(!array_key_exists($personType,recognition_person_types())||!array_key_exists($docType,recognition_document_types_for_person($personType)))throw new RuntimeException('Tipo de reconhecimento inválido.');
    if($year<2000||$year>2100)throw new RuntimeException('Ano de reconhecimento inválido.');
    if(!recognition_person($personType,$personId))throw new RuntimeException('Pessoa ou parceiro não encontrado.');
    $pdo=db();$st=$pdo->prepare("SELECT id FROM recognition_documents WHERE person_type=? AND person_id=? AND document_type=? AND recognition_year=?");$st->execute([$personType,$personId,$docType,$year]);$existing=(int)($st->fetchColumn()?:0);
    if($existing){$pdo->prepare("UPDATE recognition_documents SET status='ATIVO',notes=?,issued_at=?,issued_by=?,updated_at=? WHERE id=?")->execute([$notes,now_iso(),$adminId,now_iso(),$existing]);return $existing;}
    $serial=recognition_serial($year,$docType);$token=bin2hex(random_bytes(24));$now=now_iso();
    $pdo->prepare("INSERT INTO recognition_documents(person_type,person_id,document_type,recognition_year,serial_number,verification_token,status,notes,issued_at,issued_by,created_at,updated_at) VALUES(?,?,?,?,?,?,'ATIVO',?,?,?,?,?)")
        ->execute([$personType,$personId,$docType,$year,$serial,$token,$notes,$now,$adminId,$now,$now]);
    return (int)$pdo->lastInsertId();
}
function recognition_documents_for_person(string $type,int $id,int $year): array {
    $st=db()->prepare("SELECT * FROM recognition_documents WHERE person_type=? AND person_id=? AND recognition_year=? ORDER BY document_type");$st->execute([$type,$id,$year]);return $st->fetchAll();
}
function recognition_document(int $id): ?array { $st=db()->prepare("SELECT * FROM recognition_documents WHERE id=?");$st->execute([$id]);return $st->fetch()?:null; }
function recognition_signers(bool $activeOnly=true): array {
    $sql="SELECT * FROM recognition_signers".($activeOnly?" WHERE active=1":"")." ORDER BY sort_order,id";return db()->query($sql)->fetchAll();
}
function recognition_template_value(string $text,array $person,array $doc): string {
    $replace=['{NOME}'=>recognition_display_name($person),'{ANO}'=>(string)$doc['recognition_year'],'{FUNCAO}'=>(string)($person['role_name']??''),'{MATRICULA}'=>(string)($person['reference']??'')];
    return strtr($text,$replace);
}
function recognition_student_history(int $studentId,int $year): array {
    $sql="SELECT t.id test_id,t.title,t.test_date,t.submitted_to_admin_at,s.area,ROUND(s.final_score,1) final_score,s.total_points,
                 i.full_name instructor_name,i.preferred_name instructor_preferred,i.instructor_code
          FROM evaluation_submissions s
          JOIN evaluation_tests t ON t.id=s.test_id
          LEFT JOIN instructors i ON i.id=s.instructor_id
          WHERE s.student_id=? AND s.status='CONCLUIDA' AND t.evaluation_year=?
            AND (t.report_sent=1 OR (t.owner_instructor_id IS NULL AND t.status='ENCERRADO'))
          ORDER BY t.test_date ASC,t.id ASC";
    $st=db()->prepare($sql);$st->execute([$studentId,$year]);return $st->fetchAll();
}
function recognition_student_history_average(array $rows): ?float {
    if(!$rows)return null;$sum=0.0;foreach($rows as $r)$sum+=(float)$r['final_score'];return round($sum/count($rows),1);
}
function recognition_student_history_count(int $studentId,int $year): int {
    $st=db()->prepare("SELECT COUNT(*) FROM evaluation_submissions s JOIN evaluation_tests t ON t.id=s.test_id WHERE s.student_id=? AND s.status='CONCLUIDA' AND t.evaluation_year=? AND (t.report_sent=1 OR (t.owner_instructor_id IS NULL AND t.status='ENCERRADO'))");
    $st->execute([$studentId,$year]);return (int)$st->fetchColumn();
}

function csrf_token(): string {
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if(!hash_equals($_SESSION['csrf']??'',$_POST['csrf']??'')) exit('Solicitação inválida.');
}
function admin_count(): int { return (int)db()->query("SELECT COUNT(*) FROM admins")->fetchColumn(); }
function admin_user(): ?array {
    if(empty($_SESSION['admin_id'])) return null;
    $st=db()->prepare("SELECT id,username,display_name,role FROM admins WHERE id=?");
    $st->execute([(int)$_SESSION['admin_id']]);
    return $st->fetch() ?: null;
}
function require_admin(): array {
    $u=admin_user();
    if(!$u){ header('Location: login.php'); exit; }
    return $u;
}
function instructor_user(): ?array {
    if(empty($_SESSION['instructor_id'])) return null;
    $st=db()->prepare("SELECT id,full_name,preferred_name,role,username,instructor_code,active FROM instructors WHERE id=?");
    $st->execute([(int)$_SESSION['instructor_id']]);
    $u=$st->fetch() ?: null;
    if(!$u || (int)$u['active']!==1){ unset($_SESSION['instructor_id']); return null; }
    return $u;
}
function require_instructor(): array {
    $u=instructor_user();
    if(!$u){ header('Location: login.php'); exit; }
    return $u;
}
function instructor_record(int $id): ?array {
    $st=db()->prepare("SELECT * FROM instructors WHERE id=?");$st->execute([$id]);
    return $st->fetch() ?: null;
}
function instructor_areas(int $id): array {
    $st=db()->prepare("SELECT area FROM instructor_areas WHERE instructor_id=? ORDER BY area");$st->execute([$id]);
    return array_column($st->fetchAll(),'area');
}
function instructor_has_area(int $id,string $area): bool {
    $st=db()->prepare("SELECT COUNT(*) FROM instructor_areas WHERE instructor_id=? AND area=?");$st->execute([$id,$area]);
    return (int)$st->fetchColumn()>0;
}
function attendance_event_areas(int $eventId): array {
    $st=db()->prepare("SELECT area FROM attendance_event_areas WHERE event_id=? ORDER BY area");$st->execute([$eventId]);
    return array_column($st->fetchAll(),'area');
}
function attendance_event_has_area(int $eventId,string $area): bool {
    $st=db()->prepare("SELECT COUNT(*) FROM attendance_event_areas WHERE event_id=? AND area=?");$st->execute([$eventId,$area]);
    return (int)$st->fetchColumn()>0;
}
function attendance_area_control(int $eventId,string $area): array {
    $pdo=db();
    $st=$pdo->prepare("SELECT * FROM attendance_area_controls WHERE event_id=? AND area=?");$st->execute([$eventId,$area]);
    $r=$st->fetch();
    if($r) return $r;
    $pdo->prepare("INSERT OR IGNORE INTO attendance_area_controls(event_id,area,phase,updated_at) VALUES(?,?,'FECHADO',?)")
        ->execute([$eventId,$area,now_iso()]);
    $st=$pdo->prepare("SELECT * FROM attendance_area_controls WHERE event_id=? AND area=?");$st->execute([$eventId,$area]);
    return $st->fetch() ?: ['event_id'=>$eventId,'area'=>$area,'phase'=>'FECHADO'];
}
function attendance_phase_label(string $phase): string {
    return ['FECHADO'=>'Aguardando entrada','ENTRADA'=>'Entrada aberta','INTERVALO'=>'Entrada encerrada','SAIDA'=>'Saída aberta','CONCLUIDO'=>'Concluído'][$phase] ?? $phase;
}
function instructor_display_name(array $u): string {
    return trim((string)($u['preferred_name']??''))!=='' ? (string)$u['preferred_name'] : (string)$u['full_name'];
}
function instructor_code_for_id(int $id): string { return 'BAMAB-I-'.str_pad((string)$id,6,'0',STR_PAD_LEFT); }
function instructor_login_matches(string $identifier): array {
    $identifier=trim(preg_replace('/\s+/u',' ',$identifier) ?? $identifier);
    if($identifier==='') return [];
    $st=db()->prepare("SELECT * FROM instructors WHERE active=1 AND (username=? COLLATE NOCASE OR instructor_code=? COLLATE NOCASE OR preferred_name=? COLLATE NOCASE OR full_name=? COLLATE NOCASE) ORDER BY id LIMIT 3");
    $st->execute([$identifier,$identifier,$identifier,$identifier]);
    return $st->fetchAll();
}
function instructor_search(string $term,int $limit=30): array {
    $term=trim(preg_replace('/\s+/u',' ',$term) ?? $term);
    if($term==='') return [];
    $like='%'.str_replace(['%','_'],['\\%','\\_'],$term).'%';
    $st=db()->prepare("SELECT * FROM instructors WHERE active=1 AND (full_name LIKE ? ESCAPE '\\' COLLATE NOCASE OR preferred_name LIKE ? ESCAPE '\\' COLLATE NOCASE OR username LIKE ? ESCAPE '\\' COLLATE NOCASE OR instructor_code LIKE ? ESCAPE '\\' COLLATE NOCASE) ORDER BY full_name COLLATE NOCASE LIMIT ".max(1,min(50,$limit)));
    $st->execute([$like,$like,$like,$like]);return $st->fetchAll();
}
function instructor_master_configured(): bool { return trim(setting('instructor_master_password_hash',''))!==''; }
function instructor_master_verify(string $password): bool {
    $hash=setting('instructor_master_password_hash','');
    return $hash!=='' && password_verify($password,$hash);
}
function instructor_master_session(): bool { return !empty($_SESSION['instructor_master_admin_id']) && !empty($_SESSION['instructor_id']); }
function instructor_master_clear(): void { unset($_SESSION['instructor_master_admin_id'],$_SESSION['instructor_master_started_at'],$_SESSION['instructor_master_identifier']); }
function evaluation_rating_options(): array { return [5=>'REGULAR',8=>'BOM',9=>'ÓTIMO',10=>'EXCELENTE']; }
function evaluation_rating_label(int $score): string { $o=evaluation_rating_options(); return $o[$score]??''; }
function evaluation_default_criteria(string $area): array {
    $map=[
        'LIRA'=>[
            'Ritmo e pulsação','Precisão das notas e execução melódica','Harmonia e integração com o conjunto','Cadência contínua e estabilidade','Resposta aos comandos e à regência','Articulação e técnica de baquetas','Dinâmica e equilíbrio sonoro','Memória ou leitura da sequência musical','Postura e deslocamento em formação','Disciplina, atenção e evolução musical'
        ],
        'PRATOS'=>[
            'Ritmo e pulsação','Precisão de ataques e cortes','Cadência contínua','Sincronismo com a percussão e o conjunto','Dinâmica e controle de intensidade','Resposta aos comandos','Técnica de abertura, fechamento e controle','Postura e deslocamento em formação','Memória das sequências rítmicas','Disciplina, atenção e evolução musical'
        ],
        'BUMBO'=>[
            'Manutenção do pulso base','Ritmo e precisão dos golpes','Cadência contínua e estabilidade','Acentuação e divisão rítmica','Sincronismo com a percussão e o conjunto','Resposta aos comandos','Dinâmica e controle sonoro','Técnica de golpes e controle do instrumento','Postura, marcha e deslocamento','Disciplina, atenção e evolução musical'
        ],
        'CAIXA TENOR'=>[
            'Ritmo e pulsação','Execução de rudimentos básicos','Clareza e articulação dos golpes','Cadência contínua','Precisão de entradas, viradas e cortes','Sincronismo com a percussão e o conjunto','Resposta aos comandos','Dinâmica e controle sonoro','Postura, marcha e deslocamento','Disciplina, atenção e evolução musical'
        ],
        'QUINTOM'=>[
            'Ritmo e pulsação','Distribuição correta dos golpes entre os tambores','Precisão e clareza dos golpes','Rudimentos e frases rítmicas','Cadência contínua','Sincronismo com a percussão e o conjunto','Resposta aos comandos','Dinâmica e controle sonoro','Postura, marcha e deslocamento','Disciplina, atenção e evolução musical'
        ],
        'CORPO COREÓGRAFO'=>[
            'Ritmo e pulsação musical','Contagem e percepção da frase musical','Sincronismo com o grupo','Postura corporal','Coordenação e domínio dos movimentos','Técnica de dança e execução dos passos','Memória e execução da coreografia','Resposta aos comandos','Expressão, presença e desenvolvimento cênico','Disciplina, atenção e evolução'
        ],
        'PORTA BANDEIRA'=>[
            'Postura e apresentação','Condução e domínio da bandeira','Ritmo e cadência no deslocamento','Sincronismo com a formação','Giros, transições e movimentos','Resposta aos comandos','Alinhamento e ocupação do espaço','Apresentação e comportamento cerimonial','Cuidado e responsabilidade com o pavilhão','Disciplina, atenção e evolução'
        ],
        'PORTA-ESTANDARTE'=>[
            'Postura e apresentação','Condução e domínio do estandarte','Ritmo e cadência no deslocamento','Sincronismo com a formação','Giros, transições e movimentos','Resposta aos comandos','Alinhamento e ocupação do espaço','Apresentação e comportamento cerimonial','Cuidado e responsabilidade com o estandarte','Disciplina, atenção e evolução'
        ],
        'GUARDA DE HONRA'=>[
            'Postura e alinhamento','Ritmo e cadência de marcha','Sincronismo com o grupo','Espaçamento e manutenção da formação','Resposta aos comandos','Transições e mudanças de posição','Apresentação e comportamento cerimonial','Controle corporal e resistência','Disciplina e atenção','Desenvolvimento e evolução na função'
        ],
    ];
    return $map[$area] ?? ['Ritmo e pulsação','Harmonia e integração','Cadência contínua','Resposta aos comandos','Precisão técnica','Dinâmica e controle','Postura','Sincronismo','Memória e desenvolvimento','Disciplina e evolução'];
}
function evaluation_test(int $id): ?array { $st=db()->prepare("SELECT * FROM evaluation_tests WHERE id=?");$st->execute([$id]);return $st->fetch()?:null; }
function evaluation_test_areas(int $testId): array { $st=db()->prepare("SELECT area FROM evaluation_test_areas WHERE test_id=? ORDER BY area");$st->execute([$testId]);return array_column($st->fetchAll(),'area'); }
function evaluation_criteria(int $testId,string $area): array { $st=db()->prepare("SELECT * FROM evaluation_criteria WHERE test_id=? AND area=? ORDER BY position,id");$st->execute([$testId,$area]);return $st->fetchAll(); }
function evaluation_seed_criteria(PDO $pdo,int $testId,string $area): void {
    $check=$pdo->prepare("SELECT COUNT(*) FROM evaluation_criteria WHERE test_id=? AND area=?");$check->execute([$testId,$area]);if((int)$check->fetchColumn()>0)return;
    $ins=$pdo->prepare("INSERT INTO evaluation_criteria(test_id,area,position,criterion) VALUES(?,?,?,?)");
    foreach(evaluation_default_criteria($area) as $i=>$criterion)$ins->execute([$testId,$area,$i+1,$criterion]);
}
function evaluation_student(int $id): ?array { $st=db()->prepare("SELECT * FROM enrollments WHERE id=? AND status='APROVADA' AND deleted_at IS NULL");$st->execute([$id]);return $st->fetch()?:null; }
function evaluation_student_name(array $s): string { return display_person_name((string)($s['student_name']??''),(string)($s['preferred_name']??'')); }
function evaluation_student_phone(array $s): string {
    $raw=((int)($s['is_minor']??0)===1)?(string)($s['guardian_phone']??''):(string)($s['student_phone']??'');
    if(trim($raw)==='')$raw=(string)($s['guardian_phone']??'');
    $digits=normalize_phone_digits($raw);if(strlen($digits)===10||strlen($digits)===11)$digits='55'.$digits;
    return strlen($digits)>=12?$digits:'';
}
function evaluation_submission(int $testId,string $area,int $studentId,int $instructorId): ?array {
    $st=db()->prepare("SELECT * FROM evaluation_submissions WHERE test_id=? AND area=? AND student_id=? AND instructor_id=?");$st->execute([$testId,$area,$studentId,$instructorId]);return $st->fetch()?:null;
}
function evaluation_submission_scores(int $submissionId): array {
    $st=db()->prepare("SELECT es.*,ec.position,ec.criterion FROM evaluation_scores es JOIN evaluation_criteria ec ON ec.id=es.criterion_id WHERE es.submission_id=? ORDER BY ec.position");$st->execute([$submissionId]);return $st->fetchAll();
}
function evaluation_score_text(float $score): string { return number_format($score,1,',','.').'/10'; }
function evaluation_points_text(int $points): string { return $points.'/100 pontos'; }
function evaluation_test_owner(int $testId): ?array {
    $st=db()->prepare("SELECT i.* FROM evaluation_tests t JOIN instructors i ON i.id=t.owner_instructor_id WHERE t.id=?");$st->execute([$testId]);return $st->fetch()?:null;
}
function evaluation_test_primary_area(int $testId): string { $a=evaluation_test_areas($testId);return (string)($a[0]??''); }
function evaluation_report_is_visible_to_admin(array $test): bool { return (int)($test['report_sent']??0)===1 || (empty($test['owner_instructor_id']) && ($test['status']??'')==='ENCERRADO'); }
function evaluation_open_test_for_instructor_area(int $instructorId,string $area): ?array {
    $st=db()->prepare("SELECT t.* FROM evaluation_tests t JOIN evaluation_test_areas a ON a.test_id=t.id AND a.area=? WHERE t.owner_instructor_id=? AND t.status='ABERTO' ORDER BY t.id DESC LIMIT 1");$st->execute([$area,$instructorId]);return $st->fetch()?:null;
}
function evaluation_create_instructor_test(int $instructorId,string $area,string $title='',string $notes=''): int {
    if(!instructor_has_area($instructorId,$area))throw new RuntimeException('Esta ala não está vinculada ao seu usuário.');
    if(evaluation_open_test_for_instructor_area($instructorId,$area))throw new RuntimeException('Você já possui um teste aberto para esta ala. Finalize e envie o relatório antes de iniciar outro.');
    $title=trim($title);if($title==='')$title='AVALIAÇÃO '.mb_strtoupper($area).' — '.date('d/m/Y H:i');
    $pdo=db();$pdo->beginTransaction();try{
        $now=now_iso();$date=date('Y-m-d');$year=(int)date('Y');
        $pdo->prepare("INSERT INTO evaluation_tests(title,test_date,evaluation_year,status,notes,created_at,updated_at,created_by,owner_instructor_id,started_at,report_sent,submitted_to_admin_at,source) VALUES(?,?,?,'ABERTO',?,?,?,?,?,?,0,'','INSTRUTOR')")
            ->execute([$title,$date,$year,$notes,$now,$now,null,$instructorId,$now]);
        $id=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO evaluation_test_areas(test_id,area) VALUES(?,?)")->execute([$id,$area]);
        evaluation_seed_criteria($pdo,$id,$area);$pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function evaluation_send_report_to_admin(int $testId,int $instructorId): void {
    $test=evaluation_test($testId);if(!$test||(int)($test['owner_instructor_id']??0)!==$instructorId)throw new RuntimeException('Teste não encontrado ou não pertence ao seu usuário.');
    if(($test['status']??'')!=='ABERTO')throw new RuntimeException('Este teste já foi finalizado.');
    $st=db()->prepare("SELECT COUNT(*) FROM evaluation_submissions WHERE test_id=? AND instructor_id=? AND status='CONCLUIDA'");$st->execute([$testId,$instructorId]);
    if((int)$st->fetchColumn()<1)throw new RuntimeException('Avalie pelo menos um aluno antes de finalizar o teste.');
    db()->prepare("UPDATE evaluation_tests SET status='ENCERRADO',report_sent=1,submitted_to_admin_at=?,updated_at=? WHERE id=? AND owner_instructor_id=?")
       ->execute([now_iso(),now_iso(),$testId,$instructorId]);
}

function free_hosting_db_size(): int {
    clearstatcache(true, DB_PATH);
    return is_file(DB_PATH) ? (int)filesize(DB_PATH) : 0;
}
function free_hosting_db_percent(): float {
    if(!defined('FREE_DB_FILE_LIMIT_BYTES') || FREE_DB_FILE_LIMIT_BYTES<=0) return 0.0;
    return min(100.0,(free_hosting_db_size()/FREE_DB_FILE_LIMIT_BYTES)*100.0);
}
function free_hosting_db_warning(): bool {
    return defined('FREE_DB_WARN_BYTES') && free_hosting_db_size()>=FREE_DB_WARN_BYTES;
}
function free_hosting_mode_active(): bool {
    return defined('FREE_HOSTING_MODE') && FREE_HOSTING_MODE===true;
}

function instagram_url_valid(string $url): bool {
    $host=strtolower((string)parse_url($url,PHP_URL_HOST));
    return in_array($host,['instagram.com','www.instagram.com'],true);
}
function upload_file(array $file,string $kind='image'): string {
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return '';
    if(($file['error']??0)!==UPLOAD_ERR_OK) throw new RuntimeException('Falha no envio do arquivo.');
    $size=(int)$file['size'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    if($kind==='image'){
        $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Imagem inválida. Use JPG, PNG, WEBP ou GIF.');
        if($size>MAX_IMAGE_BYTES) throw new RuntimeException('Imagem excede 8 MB no Modo Grátis.');
        $dir=UPLOAD_ROOT.'/images'; $prefix='img';
    } else {
        $allowed=['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Vídeo inválido. Use MP4, WEBM ou MOV.');
        if($size>MAX_VIDEO_BYTES) throw new RuntimeException('Vídeo excede 8 MB no Modo Grátis. Prefira links do Instagram/YouTube para vídeos maiores.');
        $dir=UPLOAD_ROOT.'/videos'; $prefix='vid';
    }
    if(!is_dir($dir)) mkdir($dir,0775,true);
    $name=$prefix.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$allowed[$mime];
    $dest=$dir.'/'.$name;
    if(!move_uploaded_file($file['tmp_name'],$dest)) throw new RuntimeException('Não foi possível salvar o arquivo.');
    return 'uploads/'.($kind==='image'?'images':'videos').'/'.$name;
}
function delete_local_media(string $path): void {
    if($path==='' || !str_starts_with($path,'uploads/')) return;
    $full=__DIR__.'/'.$path;
    if(is_file($full)) @unlink($full);
}

function hex_from_rgb(int $r,int $g,int $b): string {
    return sprintf('#%02x%02x%02x',max(0,min(255,$r)),max(0,min(255,$g)),max(0,min(255,$b)));
}
function rgb_luminance(array $c): float {
    return 0.2126*$c[0]+0.7152*$c[1]+0.0722*$c[2];
}
function rgb_saturation(array $c): float {
    $max=max($c);$min=min($c);
    return $max===0?0:(($max-$min)/$max);
}
function image_palette(string $relativePath): array {
    $full=__DIR__.'/'.ltrim($relativePath,'/');
    if(!is_file($full) || !extension_loaded('gd')) return [];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($full) ?: '';
    $im=match($mime){
        'image/jpeg'=>@imagecreatefromjpeg($full),
        'image/png'=>@imagecreatefrompng($full),
        'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($full):false,
        'image/gif'=>@imagecreatefromgif($full),
        default=>false
    };
    if(!$im) return [];
    $small=imagecreatetruecolor(64,64);
    imagecopyresampled($small,$im,0,0,0,0,64,64,imagesx($im),imagesy($im));
    imagedestroy($im);
    $bins=[];
    for($y=0;$y<64;$y+=2){
        for($x=0;$x<64;$x+=2){
            $rgb=imagecolorat($small,$x,$y);
            $r=($rgb>>16)&255;$g=($rgb>>8)&255;$b=$rgb&255;
            // quantize to 32-step buckets
            $qr=(int)(round($r/32)*32);$qg=(int)(round($g/32)*32);$qb=(int)(round($b/32)*32);
            $qr=min(255,$qr);$qg=min(255,$qg);$qb=min(255,$qb);
            $lum=rgb_luminance([$qr,$qg,$qb]);
            if($lum>245 || $lum<12) continue;
            $key="$qr,$qg,$qb";
            $bins[$key]=($bins[$key]??0)+1;
        }
    }
    imagedestroy($small);
    arsort($bins);
    $colors=[];
    foreach(array_slice($bins,0,20,true) as $key=>$count){
        $c=array_map('intval',explode(',',$key));
        $colors[]=['rgb'=>$c,'count'=>$count,'lum'=>rgb_luminance($c),'sat'=>rgb_saturation($c)];
    }
    if(!$colors) return [];
    // primary: most frequent medium/dark color
    $primary=$colors[0]['rgb'];
    foreach($colors as $c){ if($c['lum']<175 && $c['sat']>.18){$primary=$c['rgb'];break;} }
    // secondary: brightest saturated prominent color (good for gold/yellow accents)
    usort($colors,fn($a,$b)=>($b['sat']*$b['lum']*$b['count'])<=>($a['sat']*$a['lum']*$a['count']));
    $secondary=$colors[0]['rgb'];
    // dark derived from primary
    $dark=array_map(fn($v)=>(int)max(5,$v*.22),$primary);
    return [
        'primary'=>hex_from_rgb(...$primary),
        'secondary'=>hex_from_rgb(...$secondary),
        'dark'=>hex_from_rgb(...$dark),
        'light'=>'#f8f5ee',
    ];
}
function apply_palette_from_logo(string $path): array {
    $pal=image_palette($path);
    if(!$pal) throw new RuntimeException('Não foi possível detectar as cores automaticamente. Verifique se a extensão GD está ativa.');
    set_setting('primary_color',$pal['primary']);
    set_setting('secondary_color',$pal['secondary']);
    set_setting('dark_color',$pal['dark']);
    set_setting('light_color',$pal['light']);
    return $pal;
}
function safe_zip_entry_name(string $name): string {
    $name=str_replace('\\','/',$name);
    $name=preg_replace('#/+#','/',$name);
    return ltrim($name,'/');
}
function instagram_export_import(string $zipTmp): array {
    if(!class_exists('ZipArchive')) throw new RuntimeException('A extensão ZIP do PHP precisa estar ativa no XAMPP.');
    $zip=new ZipArchive();
    if($zip->open($zipTmp)!==true) throw new RuntimeException('Não foi possível abrir o arquivo ZIP.');
    $pdo=db();$countImages=0;$countVideos=0;$logoCandidate='';$errors=0;
    for($i=0;$i<$zip->numFiles;$i++){
        $stat=$zip->statIndex($i);
        if(!$stat) continue;
        $name=safe_zip_entry_name((string)$stat['name']);
        if($name==='' || str_ends_with($name,'/')) continue;
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $isImage=in_array($ext,['jpg','jpeg','png','webp','gif'],true);
        $isVideo=in_array($ext,['mp4','webm','mov'],true);
        if(!$isImage && !$isVideo) continue;
        $stream=$zip->getStream($stat['name']);
        if(!$stream){$errors++;continue;}
        $data=stream_get_contents($stream);fclose($stream);
        if($data===false || strlen($data)<100){$errors++;continue;}
        $kind=$isImage?'images':'videos';
        $prefix=$isImage?'igimg':'igvid';
        $safeExt=$ext==='jpeg'?'jpg':$ext;
        $filename=$prefix.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$safeExt;
        $dir=UPLOAD_ROOT.'/'.$kind;
        if(!is_dir($dir)) mkdir($dir,0775,true);
        $full=$dir.'/'.$filename;
        if(file_put_contents($full,$data,LOCK_EX)===false){$errors++;continue;}
        $relative='uploads/'.$kind.'/'.$filename;

        $lower=strtolower($name);
        $looksProfile=$isImage && (
            str_contains($lower,'profile') ||
            str_contains($lower,'avatar') ||
            str_contains($lower,'perfil')
        );
        if($looksProfile && $logoCandidate==='') $logoCandidate=$relative;

        $title='Instagram BAMAB';
        $caption='Importado do arquivo oficial de exportação do Instagram.';
        $pdo->prepare("INSERT INTO media(title,caption,media_type,file_path,event_date,featured,sort_order,published,created_at) VALUES(?,?,?,?,?,0,100,1,?)")
            ->execute([$title,$caption,$isImage?'IMAGE':'VIDEO',$relative,'',now_iso()]);
        if($isImage)$countImages++; else $countVideos++;
    }
    $zip->close();
    return ['images'=>$countImages,'videos'=>$countVideos,'logo_candidate'=>$logoCandidate,'errors'=>$errors];
}


function normalized_name(string $name): string {
    $name=trim(preg_replace('/\s+/u',' ',$name) ?? $name);
    return function_exists('mb_strtolower') ? mb_strtolower($name,'UTF-8') : strtolower($name);
}
function enrollment_instruments(): array {
    return ['LIRA','PRATOS','BUMBO','CAIXA TENOR','QUINTOM','CORPO COREÓGRAFO','PORTA BANDEIRA','PORTA-ESTANDARTE','GUARDA DE HONRA'];
}
function normalize_cpf(string $cpf): string {
    return preg_replace('/\D+/','',$cpf) ?? '';
}

function cpf_valid(string $cpf): bool {
    $cpf=normalize_cpf($cpf);
    if(strlen($cpf)!==11 || preg_match('/^(\d)\1{10}$/',$cpf)) return false;
    for($t=9;$t<11;$t++){
        $sum=0;
        for($i=0;$i<$t;$i++) $sum += ((int)$cpf[$i])*(($t+1)-$i);
        $digit=(10*($sum%11))%11;
        if($digit===10) $digit=0;
        if((int)$cpf[$t]!==$digit) return false;
    }
    return true;
}
function save_camera_data_url(string $dataUrl,string $prefix='cam'): string {
    $dataUrl=trim($dataUrl);
    if($dataUrl==='') return '';
    if(!preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\r\n]+)$#',$dataUrl,$m))
        throw new RuntimeException('Foto capturada pela câmera é inválida.');
    $map=['jpeg'=>'jpg','png'=>'png','webp'=>'webp'];
    $raw=base64_decode(preg_replace('/\s+/','',$m[2]),true);
    if($raw===false || strlen($raw)<1000) throw new RuntimeException('Não foi possível processar a foto da câmera.');
    if(strlen($raw)>MAX_IMAGE_BYTES) throw new RuntimeException('Foto capturada excede 8 MB no Modo Grátis.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '';
    $expected=['jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$m[1]];
    if($mime!==$expected) throw new RuntimeException('Formato da foto capturada não corresponde ao conteúdo.');
    $dir=UPLOAD_ROOT.'/images'; if(!is_dir($dir)) mkdir($dir,0775,true);
    $name=$prefix.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$map[$m[1]];
    if(file_put_contents($dir.'/'.$name,$raw,LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar a foto capturada.');
    return 'uploads/images/'.$name;
}
function receive_required_photo(string $fileKey,string $cameraKey,string $prefix='photo'): string {
    if(!empty($_FILES[$fileKey]['name'])) return upload_file($_FILES[$fileKey],'image');
    $camera=(string)($_POST[$cameraKey]??'');
    if(trim($camera)!=='') return save_camera_data_url($camera,$prefix);
    throw new RuntimeException('A foto é obrigatória. Escolha da galeria ou tire uma foto pela câmera.');
}
function birth_age(string $birthDate, ?DateTimeImmutable $today=null): int {
    $today=$today ?: new DateTimeImmutable('today');
    $birth=DateTimeImmutable::createFromFormat('!Y-m-d',$birthDate);
    if(!$birth) return -1;
    return $birth->diff($today)->y;
}
function enrollment_protocol(): string {
    $year=date('Y');
    do {
        $protocol='BAMAB-'.$year.'-'.strtoupper(bin2hex(random_bytes(3)));
        $st=db()->prepare("SELECT COUNT(*) FROM enrollments WHERE protocol=?");
        $st->execute([$protocol]);
    } while((int)$st->fetchColumn()>0);
    return $protocol;
}
function enrollment_public_token(): string {
    return bin2hex(random_bytes(24));
}
function enrollment_statuses(): array {
    return ['PENDENTE'=>'Pendente','APROVADA'=>'Aprovada','LISTA_ESPERA'=>'Lista de espera','RECUSADA'=>'Recusada','CANCELADA'=>'Cancelada','EXCLUIDA'=>'Excluída'];
}
function enrollment_status_label(string $status): string {
    $all=enrollment_statuses();
    return $all[$status] ?? $status;
}
function enrollment_count(string $where='1=1', array $params=[]): int {
    $st=db()->prepare("SELECT COUNT(*) FROM enrollments WHERE $where");
    $st->execute($params);
    return (int)$st->fetchColumn();
}

function is_general_admin(?array $u=null): bool {
    $u=$u ?: admin_user();
    return $u && (($u['role']??'ADMIN_GERAL')==='ADMIN_GERAL');
}
function enrollment_period(int $id): ?array {
    if($id<=0) return null;
    $st=db()->prepare("SELECT * FROM enrollment_periods WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function latest_enrollment_period(): ?array {
    return db()->query("SELECT * FROM enrollment_periods ORDER BY id DESC LIMIT 1")->fetch() ?: null;
}
function active_enrollment_period(bool $respectDates=true): ?array {
    $sql="SELECT * FROM enrollment_periods WHERE active=1";
    $params=[];
    if($respectDates){
        $today=date('Y-m-d');
        $sql.=" AND start_date<=? AND end_date>=?";
        $params=[$today,$today];
    }
    $sql.=" ORDER BY id DESC LIMIT 1";
    $st=db()->prepare($sql);$st->execute($params);
    return $st->fetch() ?: null;
}
function period_is_open(?array $p): bool {
    if(!$p || (int)$p['active']!==1) return false;
    $today=date('Y-m-d');
    return $p['start_date'] <= $today && $p['end_date'] >= $today;
}
function period_status_label(array $p): string {
    $today=date('Y-m-d');
    if((int)$p['active']===1 && $p['start_date']<=$today && $p['end_date']>=$today) return 'ABERTO';
    if($p['end_date']<$today) return 'ENCERRADO';
    if($p['start_date']>$today) return (int)$p['active']===1?'AGENDADO':'INATIVO';
    return 'INATIVO';
}
function enrollment_period_counts(int $periodId): array {
    $counts=array_fill_keys(enrollment_instruments(),0);
    $st=db()->prepare("SELECT instrument,COUNT(*) total FROM enrollments
        WHERE period_id=? AND deleted_at IS NULL GROUP BY instrument");
    $st->execute([$periodId]);
    foreach($st->fetchAll() as $r) if(isset($counts[$r['instrument']])) $counts[$r['instrument']]=(int)$r['total'];
    return $counts;
}
function enrollment_card(int $enrollmentId): ?array {
    $st=db()->prepare("SELECT * FROM student_cards WHERE enrollment_id=?");
    $st->execute([$enrollmentId]);
    return $st->fetch() ?: null;
}
function card_valid_until(string $createdAt): string {
    try { return (new DateTimeImmutable($createdAt))->modify('+1 year')->format('Y-m-d'); }
    catch(Throwable $e) { return date('Y-m-d',strtotime('+1 year')); }
}
function issue_or_activate_card(int $enrollmentId,?int $adminId=null): array {
    $pdo=db();
    $st=$pdo->prepare("SELECT * FROM enrollments WHERE id=?");
    $st->execute([$enrollmentId]);$e=$st->fetch();
    if(!$e || !empty($e['deleted_at'])) throw new RuntimeException('Matrícula inexistente ou excluída.');
    if($e['status']!=='APROVADA') throw new RuntimeException('A carteirinha só pode ser emitida para matrícula APROVADA.');
    if(trim((string)$e['photo_path'])==='') throw new RuntimeException('Cadastre a foto 3x4 antes de emitir a carteirinha.');
    $valid=card_valid_until($e['created_at']);
    $now=now_iso();
    $card=enrollment_card($enrollmentId);
    if($card){
        $pdo->prepare("UPDATE student_cards SET status='ATIVA',issued_at=?,valid_until=?,deactivated_at=NULL,deactivation_reason='',issued_by=? WHERE enrollment_id=?")
            ->execute([$now,$valid,$adminId,$enrollmentId]);
    }else{
        $pdo->prepare("INSERT INTO student_cards(enrollment_id,issued_at,valid_until,status,issued_by) VALUES(?,?,?,'ATIVA',?)")
            ->execute([$enrollmentId,$now,$valid,$adminId]);
    }
    if((int)$e['is_minor']===1) issue_or_activate_guardian_card($enrollmentId,$adminId);
    return enrollment_card($enrollmentId) ?: [];
}
function deactivate_card(int $enrollmentId,string $reason=''): void {
    db()->prepare("UPDATE student_cards SET status='INATIVA',deactivated_at=?,deactivation_reason=? WHERE enrollment_id=?")
        ->execute([now_iso(),$reason,$enrollmentId]);
    deactivate_guardian_card($enrollmentId,$reason);
}

function guardian_card(int $enrollmentId): ?array {
    $st=db()->prepare("SELECT * FROM guardian_cards WHERE enrollment_id=?");
    $st->execute([$enrollmentId]); return $st->fetch() ?: null;
}
function companion_number_from_id(int $id,string $year): string {
    return 'BAMAB-AC-'.$year.'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
}
function ensure_guardian_qr_token(int $enrollmentId): string {
    $pdo=db();$st=$pdo->prepare("SELECT guardian_qr_token FROM enrollments WHERE id=?");$st->execute([$enrollmentId]);
    $token=(string)$st->fetchColumn();
    if($token===''){
        $token=bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE enrollments SET guardian_qr_token=? WHERE id=?")->execute([$token,$enrollmentId]);
    }
    return $token;
}
function guardian_qr_payload(array $e): string {
    $token=(string)($e['guardian_qr_token']??'');
    if($token==='') $token=ensure_guardian_qr_token((int)$e['id']);
    return 'BAMAB:A:'.$token;
}
function guardian_requirements_ok(array $e): array {
    if((int)$e['is_minor']!==1) return [true,''];
    if(mb_strlen(trim((string)$e['guardian_name']))<3) return [false,'Informe o nome completo do responsável.'];
    if(!cpf_valid((string)$e['guardian_cpf'])) return [false,'CPF do responsável é inválido.'];
    $age=birth_age((string)$e['guardian_birth_date']);
    if($age<18) return [false,'O responsável precisa ter 18 anos ou mais.'];
    if(mb_strlen(trim((string)$e['guardian_phone']))<8) return [false,'Informe o telefone do responsável.'];
    if(trim((string)$e['guardian_email'])==='') return [false,'Informe o e-mail do responsável.'];
    if(trim((string)$e['guardian_relationship'])==='') return [false,'Informe o vínculo do responsável.'];
    if(trim((string)$e['guardian_address'])==='' || trim((string)$e['guardian_city'])==='') return [false,'Informe endereço e cidade do responsável.'];
    if(trim((string)$e['guardian_photo_path'])==='') return [false,'Cadastre a foto 3x4 do responsável/acompanhante.'];
    return [true,''];
}
function issue_or_activate_guardian_card(int $enrollmentId,?int $adminId=null): ?array {
    $pdo=db();$st=$pdo->prepare("SELECT * FROM enrollments WHERE id=?");$st->execute([$enrollmentId]);$e=$st->fetch();
    if(!$e || (int)$e['is_minor']!==1) return null;
    [$ok,$reason]=guardian_requirements_ok($e); if(!$ok) throw new RuntimeException($reason);
    $valid=card_valid_until($e['created_at']);$now=now_iso();$card=guardian_card($enrollmentId);
    $year=substr((string)$e['created_at'],0,4) ?: date('Y');
    $number=$card['companion_number']??companion_number_from_id($enrollmentId,$year);
    ensure_guardian_qr_token($enrollmentId);
    if($card){
        $pdo->prepare("UPDATE guardian_cards SET status='ATIVA',issued_at=?,valid_until=?,deactivated_at=NULL,deactivation_reason='',issued_by=? WHERE enrollment_id=?")
            ->execute([$now,$valid,$adminId,$enrollmentId]);
    } else {
        $pdo->prepare("INSERT INTO guardian_cards(enrollment_id,companion_number,issued_at,valid_until,status,issued_by) VALUES(?,?,?,?, 'ATIVA',?)")
            ->execute([$enrollmentId,$number,$now,$valid,$adminId]);
    }
    return guardian_card($enrollmentId);
}
function deactivate_guardian_card(int $enrollmentId,string $reason=''): void {
    db()->prepare("UPDATE guardian_cards SET status='INATIVA',deactivated_at=?,deactivation_reason=? WHERE enrollment_id=?")
        ->execute([now_iso(),$reason,$enrollmentId]);
}
function registration_number_from_id(int $id,string $year): string {
    return 'BAMAB-'.$year.'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
}

function ensure_student_qr_token(int $id): string {
    $pdo=db();$st=$pdo->prepare("SELECT qr_token FROM enrollments WHERE id=?");$st->execute([$id]);
    $token=(string)$st->fetchColumn();
    if($token===''){
        $token=bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE enrollments SET qr_token=? WHERE id=?")->execute([$token,$id]);
    }
    return $token;
}
function student_qr_payload(array $e): string {
    $token=(string)($e['qr_token']??'');
    if($token==='') $token=ensure_student_qr_token((int)$e['id']);
    return 'BAMAB:S:'.$token;
}
function team_qr_payload(array $m): string { return 'BAMAB:E:'.(string)$m['qr_token']; }
function display_person_name(string $full,string $preferred=''): string {
    $preferred=trim($preferred);
    return $preferred!=='' ? $preferred : $full;
}
function school_networks(): array {
    return [
        'ESTADUAL'=>'REDE ESTADUAL',
        'MUNICIPAL'=>'REDE MUNICIPAL',
        'PARTICULAR'=>'REDE PARTICULAR DE ENSINO'
    ];
}
function school_network_label(string $value): string {
    $a=school_networks(); return $a[$value] ?? $value;
}
function report_location_line(): string {
    $city=trim(setting('report_city','Santa Luzia do Paruá'));
    $state=trim(setting('report_state','Maranhão'));
    if($city!=='' && $state!=='') return $city.' — '.$state;
    return $city!==''?$city:$state;
}
function bamab_student_card_display_name(string $full,string $preferred='',string $mode='first_last_surname'): string {
    $full=trim(preg_replace('/\s+/u',' ', $full)??$full);
    $preferred=trim(preg_replace('/\s+/u',' ', $preferred)??$preferred);
    if($full==='') return $preferred;
    $parts=preg_split('/\s+/u',$full,-1,PREG_SPLIT_NO_EMPTY)?:[];
    if(count($parts)<=1) return $parts[0]??$preferred;
    // Se APELIDO/NOME SOCIAL foi escolhido mas ficou vazio, usa primeiro nome + último sobrenome.
    if($mode==='nickname' && $preferred!=='') return $preferred;
    $particles=['da','de','do','das','dos','e','d’','d\'','del','di'];
    $first=$parts[0];
    $last=$parts[count($parts)-1];
    if($mode==='first_first_surname'){
        $surname='';
        for($i=1;$i<count($parts);$i++){
            if(!in_array(mb_strtolower($parts[$i],'UTF-8'),$particles,true)){$surname=$parts[$i];break;}
        }
        if($surname==='') $surname=$last;
    } else {
        $surname=$last;
        for($i=count($parts)-1;$i>=1;$i--){
            if(!in_array(mb_strtolower($parts[$i],'UTF-8'),$particles,true)){$surname=$parts[$i];break;}
        }
    }
    return trim($first.' '.$surname);
}
function team_roles(bool $activeOnly=true): array {
    $sql="SELECT * FROM team_roles".($activeOnly?" WHERE active=1":"")." ORDER BY sort_order,name";
    return db()->query($sql)->fetchAll();
}
function team_role(int $id): ?array {
    $st=db()->prepare("SELECT * FROM team_roles WHERE id=?");$st->execute([$id]);
    return $st->fetch() ?: null;
}
function team_statuses(): array {
    return ['PENDENTE'=>'Pendente','APROVADO'=>'Aprovado','LISTA_ESPERA'=>'Lista de espera','RECUSADO'=>'Recusado','INATIVO'=>'Inativo','CANCELADO'=>'Cancelado'];
}
function team_status_label(string $s): string { $a=team_statuses(); return $a[$s]??$s; }
function team_application_number(int $id): string {
    return 'BAMAB-EQ-'.date('Y').'-'.str_pad((string)$id,5,'0',STR_PAD_LEFT);
}
function team_member(int $id): ?array {
    $st=db()->prepare("SELECT tm.*,tr.name role_name,tr.category role_category FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.id=?");
    $st->execute([$id]);return $st->fetch() ?: null;
}
function team_badge_valid_until(string $createdAt): string {
    try{return (new DateTimeImmutable($createdAt))->modify('+1 year')->format('Y-m-d');}
    catch(Throwable $e){return date('Y-m-d',strtotime('+1 year'));}
}
function activate_team_documents(int $id): void {
    $pdo=db();$m=team_member($id);
    if(!$m) throw new RuntimeException('Membro da equipe não encontrado.');
    if($m['status']!=='APROVADO') throw new RuntimeException('O membro precisa estar APROVADO.');
    if(trim((string)$m['photo_path'])==='') throw new RuntimeException('Cadastre a foto 3x4 antes de ativar os documentos.');
    $base=$m['approved_at'] ?: $m['created_at'];
    $valid=team_badge_valid_until($base);
    $pdo->prepare("UPDATE team_members SET badge_status='ATIVO',badge_issued_at=?,badge_valid_until=?,updated_at=? WHERE id=?")
        ->execute([now_iso(),$valid,now_iso(),$id]);
}
function deactivate_team_documents(int $id): void {
    db()->prepare("UPDATE team_members SET badge_status='INATIVO',updated_at=? WHERE id=?")->execute([now_iso(),$id]);
}
function bamab_weekdays(): array {
    return [1=>'SEGUNDA-FEIRA',2=>'TERÇA-FEIRA',3=>'QUARTA-FEIRA',4=>'QUINTA-FEIRA',5=>'SEXTA-FEIRA',6=>'SÁBADO',7=>'DOMINGO'];
}
function bamab_rehearsal_names(): array {
    return [
        'ENSAIO SEMANAL',
        'ENSAIO POR ALA',
        'ENSAIO GERAL',
        'ENSAIO PRA CAMPEONATO'
    ];
}
function instructor_frequency_for_area(int $instructorId,string $area): array {
    if(!instructor_has_area($instructorId,$area)) return [];
    $pdo=db();
    $st=$pdo->prepare("SELECT e.id,e.student_name,e.preferred_name,e.registration_number
        FROM enrollments e JOIN student_cards c ON c.enrollment_id=e.id
        WHERE e.deleted_at IS NULL AND e.status='APROVADA' AND c.status='ATIVA' AND e.instrument=?
        ORDER BY e.student_name COLLATE NOCASE");
    $st->execute([$area]);$students=$st->fetchAll();

    $st=$pdo->prepare("SELECT DISTINCT ae.id
        FROM attendance_events ae
        JOIN attendance_event_areas aea ON aea.event_id=ae.id
        WHERE aea.area=? AND ae.event_date<=? AND ae.control_mode IN ('ALA_DUAL','ALA_DUAL_WEEKDAY')
        ORDER BY ae.event_date,ae.id");
    $st->execute([$area,today_bamab_date()]);
    $events=array_map('intval',array_column($st->fetchAll(),'id'));
    $total=count($events);

    $out=[];
    foreach($students as $s){
        $complete=0;$entryOnly=0;
        if($events){
            $marks=implode(',',array_fill(0,count($events),'?'));
            $params=$events;$params[]=(int)$s['id'];$params[]=$area;
            $q=$pdo->prepare("SELECT event_id,
                    SUM(CASE WHEN check_type='ENTRADA' THEN 1 ELSE 0 END) has_entry,
                    SUM(CASE WHEN check_type='SAIDA' THEN 1 ELSE 0 END) has_exit
                FROM attendance_checks
                WHERE event_id IN ($marks) AND person_type='ALUNO' AND person_id=? AND area=?
                GROUP BY event_id");
            $q->execute($params);
            foreach($q->fetchAll() as $r){
                if((int)$r['has_entry']>0 && (int)$r['has_exit']>0)$complete++;
                elseif((int)$r['has_entry']>0)$entryOnly++;
            }
        }
        $absent=max(0,$total-$complete-$entryOnly);
        $pct=$total>0?round(($complete/$total)*100,1):0;
        $out[]=[
            'id'=>(int)$s['id'],'name'=>$s['student_name'],'preferred'=>$s['preferred_name'],
            'number'=>$s['registration_number'],'total'=>$total,'complete'=>$complete,
            'entry_only'=>$entryOnly,'absent'=>$absent,'percentage'=>$pct
        ];
    }
    return $out;
}
function bamab_weekday_label(int $weekday): string {
    $days=bamab_weekdays(); return $days[$weekday] ?? 'DIA INVÁLIDO';
}
function attendance_schedule(int $id): ?array {
    $st=db()->prepare("SELECT * FROM attendance_schedules WHERE id=?");$st->execute([$id]);
    return $st->fetch() ?: null;
}
function attendance_schedule_areas(int $scheduleId): array {
    $st=db()->prepare("SELECT area FROM attendance_schedule_areas WHERE schedule_id=? ORDER BY area");$st->execute([$scheduleId]);
    return array_column($st->fetchAll(),'area');
}
function today_bamab_date(): string { return date('Y-m-d'); }
function today_bamab_weekday(): int { return (int)date('N'); }
function ensure_today_attendance_events(?int $instructorId=null): array {
    $pdo=db();$today=today_bamab_date();$weekday=today_bamab_weekday();
    $st=$pdo->prepare("SELECT * FROM attendance_schedules WHERE active=1 AND weekday=? ORDER BY start_time,id");$st->execute([$weekday]);
    $schedules=$st->fetchAll();$allowedAreas=$instructorId?instructor_areas($instructorId):[];$result=[];
    foreach($schedules as $s){
        $areas=attendance_schedule_areas((int)$s['id']);
        if($instructorId!==null && !array_intersect($areas,$allowedAreas)) continue;
        $find=$pdo->prepare("SELECT * FROM attendance_events WHERE schedule_id=? AND event_date=? LIMIT 1");$find->execute([(int)$s['id'],$today]);$event=$find->fetch();
        if(!$event){
            $pdo->beginTransaction();
            try{
                $pdo->prepare("INSERT INTO attendance_events(title,event_date,start_time,end_time,active,notes,created_at,created_by,event_type,control_mode,schedule_id,include_students,include_instructors,include_team,include_companions) VALUES(?,?,?,?,1,?,?,?,?, 'ALA_DUAL_WEEKDAY',?,?,?,?,?)")
                    ->execute([$s['title'],$today,$s['start_time'],$s['end_time'],$s['notes'],now_iso(),$s['created_by'],$s['event_type'],(int)$s['id'],(int)($s['include_students']??1),(int)($s['include_instructors']??1),(int)($s['include_team']??0),(int)($s['include_companions']??0)]);
                $eventId=(int)$pdo->lastInsertId();
                $ia=$pdo->prepare("INSERT OR IGNORE INTO attendance_event_areas(event_id,area) VALUES(?,?)");
                $ic=$pdo->prepare("INSERT OR IGNORE INTO attendance_area_controls(event_id,area,phase,updated_at) VALUES(?,?,'FECHADO',?)");
                foreach($areas as $area){$ia->execute([$eventId,$area]);$ic->execute([$eventId,$area,now_iso()]);}
                $pdo->commit();
                $find=$pdo->prepare("SELECT * FROM attendance_events WHERE id=?");$find->execute([$eventId]);$event=$find->fetch();
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                // Se outro acesso criou no mesmo instante, reaproveita o registro existente.
                $find=$pdo->prepare("SELECT * FROM attendance_events WHERE schedule_id=? AND event_date=? LIMIT 1");$find->execute([(int)$s['id'],$today]);$event=$find->fetch();
                if(!$event) throw $e;
            }
        }
        if($event) $result[]=$event;
    }
    return $result;
}
function active_attendance_event(): ?array {
    return db()->query("SELECT * FROM attendance_events WHERE active=1 ORDER BY id DESC LIMIT 1")->fetch() ?: null;
}

function ensure_instructor_qr_token(int $id): string {
    $pdo=db();$st=$pdo->prepare("SELECT qr_token FROM instructors WHERE id=?");$st->execute([$id]);
    $token=(string)$st->fetchColumn();
    if($token===''){
        $token=bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE instructors SET qr_token=?,updated_at=? WHERE id=?")->execute([$token,now_iso(),$id]);
    }
    return $token;
}
function instructor_qr_payload(array $i): string {
    $token=(string)($i['qr_token']??'');
    if($token==='') $token=ensure_instructor_qr_token((int)$i['id']);
    return 'BAMAB:I:'.$token;
}

function attendance_general_control(int $eventId): array {
    $pdo=db();
    $st=$pdo->prepare("SELECT * FROM attendance_general_controls WHERE event_id=?");$st->execute([$eventId]);
    $r=$st->fetch();
    if($r) return $r;
    $pdo->prepare("INSERT OR IGNORE INTO attendance_general_controls(event_id,phase,updated_at) VALUES(?,'FECHADO',?)")
        ->execute([$eventId,now_iso()]);
    $st=$pdo->prepare("SELECT * FROM attendance_general_controls WHERE event_id=?");$st->execute([$eventId]);
    return $st->fetch() ?: ['event_id'=>$eventId,'phase'=>'FECHADO'];
}
function attendance_general_set_phase(int $eventId,string $action,int $adminId): string {
    $pdo=db();$c=attendance_general_control($eventId);$phase=(string)($c['phase']??'FECHADO');$now=now_iso();
    if($action==='open_entry'){
        if($phase!=='FECHADO') throw new RuntimeException('A entrada só pode ser aberta em um controle novo ou reiniciado.');
        $st=$pdo->prepare("SELECT * FROM attendance_events WHERE id=?");$st->execute([$eventId]);$event=$st->fetch();
        if(!$event) throw new RuntimeException('Atividade não encontrada.');
        if((int)($event['active']??0)!==1) throw new RuntimeException('Esta atividade está encerrada. Reinicie-a antes de abrir uma nova entrada.');
        if((string)$event['event_date']!==today_bamab_date()) throw new RuntimeException('A entrada só pode ser aberta na data da atividade: '.date('d/m/Y',strtotime((string)$event['event_date'])).'.');
        attendance_freeze_roster($event);
        $pdo->prepare("UPDATE attendance_general_controls SET phase='ENTRADA',entry_opened_at=?,entry_closed_at=NULL,exit_opened_at=NULL,exit_closed_at=NULL,last_admin_id=?,updated_at=? WHERE event_id=?")
            ->execute([$now,$adminId,$now,$eventId]); return 'Entrada aberta.';
    }
    if($action==='close_entry'){
        if($phase!=='ENTRADA') throw new RuntimeException('A entrada não está aberta.');
        $pdo->prepare("UPDATE attendance_general_controls SET phase='INTERVALO',entry_closed_at=?,last_admin_id=?,updated_at=? WHERE event_id=?")
            ->execute([$now,$adminId,$now,$eventId]); return 'Entrada encerrada.';
    }
    if($action==='open_exit'){
        if($phase!=='INTERVALO') throw new RuntimeException('Encerre a entrada antes de abrir a saída.');
        $pdo->prepare("UPDATE attendance_general_controls SET phase='SAIDA',exit_opened_at=?,last_admin_id=?,updated_at=? WHERE event_id=?")
            ->execute([$now,$adminId,$now,$eventId]); return 'Saída aberta.';
    }
    if($action==='close_exit'){
        if($phase!=='SAIDA') throw new RuntimeException('A saída não está aberta.');
        $pdo->prepare("UPDATE attendance_general_controls SET phase='CONCLUIDO',exit_closed_at=?,last_admin_id=?,updated_at=? WHERE event_id=?")
            ->execute([$now,$adminId,$now,$eventId]);
        $pdo->prepare("UPDATE attendance_events SET active=0,closed_at=? WHERE id=?")->execute([$now,$eventId]);
        return 'Controle concluído.';
    }
    throw new RuntimeException('Ação de presença inválida.');
}

function attendance_identity_from_payload(string $payload): ?array {
    $pdo=db();$payload=trim($payload);
    if(preg_match('/^BAMAB:S:([a-f0-9]{16,64})$/i',$payload,$m)){
        $st=$pdo->prepare("SELECT e.* FROM enrollments e WHERE e.qr_token=? AND e.deleted_at IS NULL");
        $st->execute([$m[1]]);$r=$st->fetch();
        if(!$r) return null;
        return ['type'=>'ALUNO','id'=>(int)$r['id'],'name'=>$r['student_name'],'preferred'=>$r['preferred_name']??'','role'=>$r['instrument'],'number'=>$r['registration_number']?:$r['protocol'],'status'=>$r['status'],'area'=>$r['instrument']];
    }
    if(preg_match('/^BAMAB:A:([a-f0-9]{16,64})$/i',$payload,$m)){
        $st=$pdo->prepare("SELECT e.*,gc.companion_number,gc.status companion_status,gc.valid_until companion_valid_until
            FROM enrollments e JOIN guardian_cards gc ON gc.enrollment_id=e.id
            WHERE e.guardian_qr_token=? AND e.deleted_at IS NULL");
        $st->execute([$m[1]]);$r=$st->fetch();
        if(!$r) return null;
        return ['type'=>'ACOMPANHANTE','id'=>(int)$r['id'],'name'=>$r['guardian_name'],'preferred'=>'','role'=>'RESPONSÁVEL / '.$r['guardian_relationship'],'number'=>$r['companion_number'],'status'=>$r['companion_status'],'area'=>$r['instrument']];
    }
    if(preg_match('/^BAMAB:E:([a-f0-9]{16,64})$/i',$payload,$m)){
        $st=$pdo->prepare("SELECT tm.*,tr.name role_name FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.qr_token=? AND tm.deleted_at IS NULL");
        $st->execute([$m[1]]);$r=$st->fetch();
        if(!$r) return null;
        return ['type'=>'EQUIPE','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role_name']?:'EQUIPE','number'=>$r['application_number'],'status'=>$r['status'],'area'=>'EQUIPE'];
    }
    if(preg_match('/^BAMAB:I:([a-f0-9]{16,64})$/i',$payload,$m)){
        $st=$pdo->prepare("SELECT * FROM instructors WHERE qr_token=?");
        $st->execute([$m[1]]);$r=$st->fetch();
        if(!$r) return null;
        return ['type'=>'INSTRUTOR','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role'],'number'=>$r['instructor_code']?:instructor_code_for_id((int)$r['id']),'status'=>(int)$r['active']===1?'ATIVO':'INATIVO','areas'=>instructor_areas((int)$r['id']),'area'=>'INSTRUÇÃO'];
    }
    return null;
}
function attendance_identity_from_number(string $number): ?array {
    $pdo=db();$number=trim($number);
    $st=$pdo->prepare("SELECT * FROM enrollments WHERE (registration_number=? OR protocol=?) AND deleted_at IS NULL");
    $st->execute([$number,$number]);$r=$st->fetch();
    if($r) return ['type'=>'ALUNO','id'=>(int)$r['id'],'name'=>$r['student_name'],'preferred'=>$r['preferred_name']??'','role'=>$r['instrument'],'number'=>$r['registration_number']?:$r['protocol'],'status'=>$r['status'],'area'=>$r['instrument']];
    $st=$pdo->prepare("SELECT e.*,gc.companion_number,gc.status companion_status FROM enrollments e
        JOIN guardian_cards gc ON gc.enrollment_id=e.id
        WHERE gc.companion_number=? AND e.deleted_at IS NULL");
    $st->execute([$number]);$g=$st->fetch();
    if($g) return ['type'=>'ACOMPANHANTE','id'=>(int)$g['id'],'name'=>$g['guardian_name'],'preferred'=>'','role'=>'RESPONSÁVEL / '.$g['guardian_relationship'],'number'=>$g['companion_number'],'status'=>$g['companion_status'],'area'=>$g['instrument']];
    $st=$pdo->prepare("SELECT tm.*,tr.name role_name FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.application_number=? AND tm.deleted_at IS NULL");
    $st->execute([$number]);$r=$st->fetch();
    if($r) return ['type'=>'EQUIPE','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role_name']?:'EQUIPE','number'=>$r['application_number'],'status'=>$r['status'],'area'=>'EQUIPE'];
    $st=$pdo->prepare("SELECT * FROM instructors WHERE instructor_code=? COLLATE NOCASE OR username=? COLLATE NOCASE LIMIT 1");
    $st->execute([$number,$number]);$r=$st->fetch();
    if($r) return ['type'=>'INSTRUTOR','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role'],'number'=>$r['instructor_code']?:instructor_code_for_id((int)$r['id']),'status'=>(int)$r['active']===1?'ATIVO':'INATIVO','areas'=>instructor_areas((int)$r['id']),'area'=>'INSTRUÇÃO'];
    return null;
}

function attendance_identity_by_type_id(string $type,int $id): ?array {
    $pdo=db();$type=strtoupper($type);
    if($type==='ALUNO'){
        $st=$pdo->prepare("SELECT * FROM enrollments WHERE id=?");$st->execute([$id]);$r=$st->fetch();
        return $r?['type'=>'ALUNO','id'=>$id,'name'=>$r['student_name'],'preferred'=>$r['preferred_name']??'','role'=>$r['instrument'],'number'=>$r['registration_number']?:$r['protocol'],'status'=>$r['status'],'area'=>$r['instrument']]:null;
    }
    if($type==='ACOMPANHANTE'){
        $st=$pdo->prepare("SELECT e.*,gc.companion_number,gc.status companion_status FROM enrollments e LEFT JOIN guardian_cards gc ON gc.enrollment_id=e.id WHERE e.id=?");$st->execute([$id]);$r=$st->fetch();
        return $r?['type'=>'ACOMPANHANTE','id'=>$id,'name'=>$r['guardian_name'],'preferred'=>'','role'=>'RESPONSÁVEL / '.$r['guardian_relationship'],'number'=>$r['companion_number']??'','status'=>$r['companion_status']??'','area'=>$r['instrument']]:null;
    }
    if($type==='EQUIPE'){
        $st=$pdo->prepare("SELECT tm.*,tr.name role_name FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.id=?");$st->execute([$id]);$r=$st->fetch();
        return $r?['type'=>'EQUIPE','id'=>$id,'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role_name']?:'EQUIPE','number'=>$r['application_number'],'status'=>$r['status'],'area'=>'EQUIPE']:null;
    }
    if($type==='INSTRUTOR'){
        $r=instructor_record($id);
        return $r?['type'=>'INSTRUTOR','id'=>$id,'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'role'=>$r['role'],'number'=>$r['instructor_code']?:instructor_code_for_id($id),'status'=>(int)$r['active']===1?'ATIVO':'INATIVO','areas'=>instructor_areas($id),'area'=>'INSTRUÇÃO']:null;
    }
    return null;
}

function attendance_identity_validity(array $identity): array {
    $pdo=db();$today=today_bamab_date();$type=(string)($identity['type']??'');$id=(int)($identity['id']??0);
    if($type==='ALUNO'){
        $st=$pdo->prepare("SELECT e.status,c.status card_status,c.valid_until FROM enrollments e LEFT JOIN student_cards c ON c.enrollment_id=e.id WHERE e.id=? AND e.deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();
        if(!$r || $r['status']!=='APROVADA') return [false,'Matrícula do aluno não está APROVADA.'];
        if(($r['card_status']??'')!=='ATIVA') return [false,'Carteirinha do aluno está inativa.'];
        if(trim((string)($r['valid_until']??''))==='' || $r['valid_until']<$today) return [false,'Carteirinha do aluno está vencida.'];
        return [true,''];
    }
    if($type==='ACOMPANHANTE'){
        $st=$pdo->prepare("SELECT e.status,gc.status card_status,gc.valid_until FROM enrollments e LEFT JOIN guardian_cards gc ON gc.enrollment_id=e.id WHERE e.id=? AND e.deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();
        if(!$r || $r['status']!=='APROVADA') return [false,'Matrícula vinculada ao acompanhante não está aprovada.'];
        if(($r['card_status']??'')!=='ATIVA') return [false,'Carteirinha do acompanhante está inativa.'];
        if(trim((string)($r['valid_until']??''))==='' || $r['valid_until']<$today) return [false,'Carteirinha do acompanhante está vencida.'];
        return [true,''];
    }
    if($type==='EQUIPE'){
        $st=$pdo->prepare("SELECT status,badge_status,badge_valid_until FROM team_members WHERE id=? AND deleted_at IS NULL");$st->execute([$id]);$r=$st->fetch();
        if(!$r || $r['status']!=='APROVADO') return [false,'Membro da equipe não está APROVADO.'];
        if(($r['badge_status']??'')!=='ATIVO') return [false,'Documento da equipe está inativo.'];
        if(trim((string)($r['badge_valid_until']??''))==='' || $r['badge_valid_until']<$today) return [false,'Documento da equipe está vencido.'];
        return [true,''];
    }
    if($type==='INSTRUTOR'){
        $st=$pdo->prepare("SELECT active FROM instructors WHERE id=?");$st->execute([$id]);$active=$st->fetchColumn();
        if((int)$active!==1) return [false,'Instrutor/Auxiliar está inativo.'];
        return [true,''];
    }
    return [false,'Tipo de pessoa não reconhecido.'];
}

function attendance_event_audience(array $event): array {
    return [
        'ALUNO'=>(int)($event['include_students']??1)===1,
        'INSTRUTOR'=>(int)($event['include_instructors']??0)===1,
        'EQUIPE'=>(int)($event['include_team']??0)===1,
        'ACOMPANHANTE'=>(int)($event['include_companions']??0)===1,
    ];
}
function attendance_identity_allowed_for_event(array $event,array $identity): array {
    $aud=attendance_event_audience($event);$type=(string)($identity['type']??'');
    if(empty($aud[$type])) return [false,'Este tipo de participante não está incluído neste controle.'];
    $areas=attendance_event_areas((int)$event['id']);
    if(!$areas || $type==='EQUIPE') return [true,''];
    if($type==='INSTRUTOR'){
        $my=$identity['areas']??instructor_areas((int)$identity['id']);
        if(!array_intersect($areas,$my)) return [false,'Instrutor/Auxiliar não pertence às alas deste evento.'];
        return [true,''];
    }
    $area=(string)($identity['area']??'');
    if($area!=='' && !in_array($area,$areas,true)) return [false,'Pessoa não pertence a uma das alas deste evento.'];
    return [true,''];
}
function attendance_identity_event_area(array $event,array $identity): string {
    $type=(string)($identity['type']??'');
    if($type==='EQUIPE') return 'EQUIPE';
    if($type==='INSTRUTOR'){
        $areas=attendance_event_areas((int)$event['id']);$mine=$identity['areas']??[];
        $match=array_values(array_intersect($areas,$mine));
        return $match[0]??'INSTRUÇÃO';
    }
    return trim((string)($identity['area']??'')) ?: 'GERAL';
}
function attendance_register_check(array $event,array $identity,string $checkType,string $scanMethod='QR',?int $adminId=null,?int $instructorId=null): array {
    $pdo=db();$checkType=strtoupper($checkType);
    if(!in_array($checkType,['ENTRADA','SAIDA'],true)) throw new RuntimeException('Tipo de marcação inválido.');
    $type=(string)$identity['type'];$personId=(int)$identity['id'];$eventId=(int)$event['id'];
    if($checkType==='ENTRADA'){
        [$allowed,$why]=attendance_identity_allowed_for_event($event,$identity);if(!$allowed) throw new RuntimeException($why);
        [$valid,$why]=attendance_identity_validity($identity);if(!$valid) throw new RuntimeException($why);
    }else{
        $st=$pdo->prepare("SELECT scanned_at FROM attendance_checks WHERE event_id=? AND person_type=? AND person_id=? AND check_type='ENTRADA'");
        $st->execute([$eventId,$type,$personId]);
        if(!$st->fetchColumn()) throw new RuntimeException('Não há ENTRADA registrada para esta pessoa neste evento.');
        // A saída permanece registrável mesmo se o cadastro for desativado depois da entrada,
        // preservando a integridade do histórico daquele evento.
    }
    $st=$pdo->prepare("SELECT scanned_at FROM attendance_checks WHERE event_id=? AND person_type=? AND person_id=? AND check_type=?");
    $st->execute([$eventId,$type,$personId,$checkType]);$existing=$st->fetchColumn();
    if($existing) return ['duplicate'=>true,'time'=>date('H:i:s',strtotime((string)$existing)),'area'=>attendance_identity_event_area($event,$identity)];
    $area=attendance_identity_event_area($event,$identity);$now=now_iso();
    $pdo->prepare("INSERT INTO attendance_checks(event_id,person_type,person_id,area,check_type,scanned_at,scan_method,instructor_id,admin_id) VALUES(?,?,?,?,?,?,?,?,?)")
        ->execute([$eventId,$type,$personId,$area,$checkType,$now,$scanMethod,$instructorId,$adminId]);
    return ['duplicate'=>false,'time'=>date('H:i:s',strtotime($now)),'area'=>$area];
}

function attendance_expected_people_live(array $event): array {
    $pdo=db();$today=(string)($event['event_date']??today_bamab_date());if($today==='')$today=today_bamab_date();$areas=attendance_event_areas((int)$event['id']);$aud=attendance_event_audience($event);$out=[];
    if($aud['ALUNO']){
        $sql="SELECT e.id,e.student_name,e.preferred_name,e.registration_number,e.protocol,e.instrument FROM enrollments e JOIN student_cards c ON c.enrollment_id=e.id WHERE e.deleted_at IS NULL AND e.status='APROVADA' AND c.status='ATIVA' AND c.valid_until>=?";
        $p=[$today];
        if($areas){$marks=implode(',',array_fill(0,count($areas),'?'));$sql.=" AND e.instrument IN ($marks)";$p=array_merge($p,$areas);}
        $sql.=" ORDER BY e.student_name COLLATE NOCASE";$st=$pdo->prepare($sql);$st->execute($p);
        foreach($st->fetchAll() as $r)$out[]=['type'=>'ALUNO','id'=>(int)$r['id'],'name'=>$r['student_name'],'preferred'=>$r['preferred_name'],'number'=>$r['registration_number']?:$r['protocol'],'role'=>$r['instrument'],'area'=>$r['instrument']];
    }
    if($aud['INSTRUTOR']){
        $sql="SELECT DISTINCT i.* FROM instructors i";
        $p=[];
        if($areas){$sql.=" JOIN instructor_areas ia ON ia.instructor_id=i.id WHERE i.active=1 AND ia.area IN (".implode(',',array_fill(0,count($areas),'?')).")";$p=$areas;}
        else $sql.=" WHERE i.active=1";
        $sql.=" ORDER BY i.role,i.full_name COLLATE NOCASE";$st=$pdo->prepare($sql);$st->execute($p);
        foreach($st->fetchAll() as $r)$out[]=['type'=>'INSTRUTOR','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'number'=>$r['instructor_code']?:instructor_code_for_id((int)$r['id']),'role'=>$r['role'],'area'=>implode(' • ',instructor_areas((int)$r['id']))];
    }
    if($aud['EQUIPE']){
        $st=$pdo->prepare("SELECT tm.*,tr.name role_name FROM team_members tm LEFT JOIN team_roles tr ON tr.id=tm.role_id WHERE tm.deleted_at IS NULL AND tm.status='APROVADO' AND tm.badge_status='ATIVO' AND tm.badge_valid_until>=? ORDER BY tm.full_name COLLATE NOCASE");$st->execute([$today]);
        foreach($st->fetchAll() as $r)$out[]=['type'=>'EQUIPE','id'=>(int)$r['id'],'name'=>$r['full_name'],'preferred'=>$r['preferred_name'],'number'=>$r['application_number'],'role'=>$r['role_name']?:'EQUIPE','area'=>'EQUIPE'];
    }
    if($aud['ACOMPANHANTE']){
        $sql="SELECT e.id,e.guardian_name,e.guardian_relationship,e.instrument,gc.companion_number FROM enrollments e JOIN guardian_cards gc ON gc.enrollment_id=e.id WHERE e.deleted_at IS NULL AND e.status='APROVADA' AND e.is_minor=1 AND gc.status='ATIVA' AND gc.valid_until>=?";
        $p=[$today];
        if($areas){$marks=implode(',',array_fill(0,count($areas),'?'));$sql.=" AND e.instrument IN ($marks)";$p=array_merge($p,$areas);}
        $sql.=" ORDER BY e.guardian_name COLLATE NOCASE";$st=$pdo->prepare($sql);$st->execute($p);
        foreach($st->fetchAll() as $r)$out[]=['type'=>'ACOMPANHANTE','id'=>(int)$r['id'],'name'=>$r['guardian_name'],'preferred'=>'','number'=>$r['companion_number'],'role'=>'RESPONSÁVEL / '.$r['guardian_relationship'],'area'=>$r['instrument']];
    }
    return $out;
}


function attendance_expected_people(array $event): array {
    $pdo=db();$eventId=(int)$event['id'];
    $st=$pdo->prepare("SELECT roster_frozen_at FROM attendance_events WHERE id=?");$st->execute([$eventId]);$frozen=(string)$st->fetchColumn();
    if($frozen!==''){
        $st=$pdo->prepare("SELECT * FROM attendance_roster WHERE event_id=? ORDER BY person_type,name_snapshot COLLATE NOCASE");$st->execute([$eventId]);$rows=$st->fetchAll();
        $out=[];foreach($rows as $r)$out[]=['type'=>$r['person_type'],'id'=>(int)$r['person_id'],'name'=>$r['name_snapshot'],'preferred'=>$r['preferred_snapshot'],'number'=>$r['number_snapshot'],'role'=>$r['role_snapshot'],'area'=>$r['area_snapshot']];
        return $out;
    }
    return attendance_expected_people_live($event);
}
function attendance_freeze_roster(array $event): int {
    $pdo=db();$eventId=(int)$event['id'];
    $st=$pdo->prepare("SELECT roster_frozen_at FROM attendance_events WHERE id=?");$st->execute([$eventId]);$frozen=(string)$st->fetchColumn();
    if($frozen!==''){
        $st=$pdo->prepare("SELECT COUNT(*) FROM attendance_roster WHERE event_id=?");$st->execute([$eventId]);return (int)$st->fetchColumn();
    }
    $people=attendance_expected_people_live($event);$now=now_iso();
    $ins=$pdo->prepare("INSERT OR IGNORE INTO attendance_roster(event_id,person_type,person_id,name_snapshot,preferred_snapshot,number_snapshot,role_snapshot,area_snapshot,created_at) VALUES(?,?,?,?,?,?,?,?,?)");
    foreach($people as $p)$ins->execute([$eventId,$p['type'],(int)$p['id'],(string)$p['name'],(string)($p['preferred']??''),(string)$p['number'],(string)$p['role'],(string)($p['area']??''),$now]);
    $pdo->prepare("UPDATE attendance_events SET roster_frozen_at=? WHERE id=?")->execute([$now,$eventId]);
    return count($people);
}


function attendance_expected_students_for_area(array $event,string $area): array {
    $out=[];
    foreach(attendance_expected_people($event) as $p){
        if(($p['type']??'')==='ALUNO' && ($p['area']??'')===$area){
            $out[]=['id'=>(int)$p['id'],'student_name'=>(string)$p['name'],'preferred_name'=>(string)($p['preferred']??''),'registration_number'=>(string)$p['number']];
        }
    }
    usort($out,fn($a,$b)=>mb_strtolower($a['student_name'])<=>mb_strtolower($b['student_name']));
    return $out;
}


function render_instagram_embed(string $url): string {
    if(!instagram_url_valid($url)) return '';
    $safe=e($url);
    return '<blockquote class="instagram-media" data-instgrm-permalink="'.$safe.'" data-instgrm-version="14"><a href="'.$safe.'" target="_blank" rel="noopener">Ver publicação no Instagram</a></blockquote>';
}
db();
