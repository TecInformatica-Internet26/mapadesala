<?php
require 'conexao.php';

// Garante a coluna de foto (compatível com bancos antigos)
$chk = $conexao->query("SHOW COLUMNS FROM professores LIKE 'foto'");
if (!($chk && $chk->num_rows > 0)) {
    @ $conexao->query("ALTER TABLE professores ADD COLUMN foto VARCHAR(255) NULL");
}

function ensureDir(string $path): void {
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}


function saveProfessorPhotoFromBase64(string $dataUrl, string $uploadDirAbs): ?string {
    $dataUrl = trim($dataUrl);
    if ($dataUrl === '') return null;

    if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $dataUrl, $m)) return null;
    $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];

    $base64 = preg_replace('/^data:image\/(png|jpeg|jpg|webp);base64,/', '', $dataUrl);
    $bin = base64_decode($base64, true);
    if ($bin === false) return null;

    // Limite 2MB
    if (strlen($bin) > 2 * 1024 * 1024) {
        backWithError("A foto é muito grande (máx 2MB).");
    }

    ensureDir($uploadDirAbs);

    $name = "prof_" . date("Ymd_His") . "_" . bin2hex(random_bytes(3)) . "." . $ext;
    $dest = rtrim($uploadDirAbs, "/\\") . DIRECTORY_SEPARATOR . $name;

    if (@file_put_contents($dest, $bin) === false) return null;

    return $name;
}

function saveProfessorPhoto(array $file, string $uploadDirAbs): ?string {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;

    // Limites básicos (2MB)
    if (isset($file['size']) && (int)$file['size'] > 2 * 1024 * 1024) {
        backWithError("A foto é muito grande (máx 2MB).");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        backWithError("Formato de foto inválido. Use JPG, PNG ou WEBP.");
    }

    ensureDir($uploadDirAbs);
    $ext = $allowed[$mime];
    $name = 'prof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = rtrim($uploadDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        backWithError("Não foi possível salvar a foto.");
    }
    return $name;
}

function onlyDigits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

function formatCelular(string $digits): string {
    // Espera 11 dígitos: DDD + 9 + 8
    if (strlen($digits) !== 11) return $digits;
    $ddd = substr($digits, 0, 2);
    $n9  = substr($digits, 2, 1);
    $p1  = substr($digits, 3, 4);
    $p2  = substr($digits, 7, 4);
    return "($ddd) $n9 $p1-$p2";
}

function backWithError(string $msg): void {
    $q = http_build_query(["erro" => $msg]);
    header("Location: ../Paginas/professores.php?$q");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Paginas/professores.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = isset($_POST["idProfessor"]) ? (int)$_POST["idProfessor"] : 0;

    $nomeProfessor = trim($_POST["nomeProfessor"] ?? "");
    $formacao = trim($_POST["formacao"] ?? "");
    $telefoneRaw = trim($_POST["telefone"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $cursosComp = trim($_POST["cursosCompl"] ?? "");
    $fotoAtual = trim($_POST['fotoAtual'] ?? '');

    // Upload de foto (se houver)
    $uploadDirAbs = realpath(__DIR__ . '/../IMG') . DIRECTORY_SEPARATOR . 'professores';
    $baseImg = __DIR__ . '/../IMG/professores';
    $novaFoto = null;

    // Preferência: foto recortada (base64) vinda do modal
    $fotoCortada = trim($_POST['fotoCortada'] ?? '');
    if ($fotoCortada !== '') {
        $novaFoto = saveProfessorPhotoFromBase64($fotoCortada, $baseImg);
    } elseif (isset($_FILES['fotoProfessor'])) {
        $novaFoto = saveProfessorPhoto($_FILES['fotoProfessor'], $baseImg);
    }
// Se substituiu a foto, apaga a antiga
    $fotoFinal = $fotoAtual;

    // Foto recortada (base64 vindo do modal)
    $fotoCortada = trim($_POST['fotoCortada'] ?? '');

    if ($novaFoto) {
        $fotoFinal = $novaFoto;
        if ($fotoAtual) {
            $old = __DIR__ . '/../IMG/professores/' . $fotoAtual;
            if (is_file($old)) @unlink($old);
        }
    }

    // Validações básicas
    if ($nomeProfessor === "") backWithError("Nome do professor é obrigatório.");

    $telDigits = onlyDigits($telefoneRaw);
    if (strlen($telDigits) !== 11) {
        backWithError("Telefone inválido. Use (99) 9 9999-9999.");
    }
    $telefone = formatCelular($telDigits);

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        backWithError("E-mail inválido.");
    }

    // Unicidade: nome/email/telefone (ignora o próprio id no update)
    // Nome
    $sql = "SELECT id_professor FROM professores WHERE LOWER(nome) = LOWER(?) AND id_professor <> ? LIMIT 1";
    $st = $conexao->prepare($sql);
    $st->bind_param("si", $nomeProfessor, $id);
    $st->execute();
    $st->store_result();
    if ($st->num_rows > 0) { $st->close(); backWithError("Já existe um professor com esse nome."); }
    $st->close();

    // Email (se houver)
    if ($email !== "") {
        $sql = "SELECT id_professor FROM professores WHERE LOWER(email) = LOWER(?) AND id_professor <> ? LIMIT 1";
        $st = $conexao->prepare($sql);
        $st->bind_param("si", $email, $id);
        $st->execute();
        $st->store_result();
        if ($st->num_rows > 0) { $st->close(); backWithError("Já existe um professor com esse e-mail."); }
        $st->close();
    }

    // Telefone (compara por dígitos)
    $sql = "SELECT id_professor
            FROM professores
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),' ',''),'-','') = ?
              AND id_professor <> ?
            LIMIT 1";
    $st = $conexao->prepare($sql);
    $st->bind_param("si", $telDigits, $id);
    $st->execute();
    $st->store_result();
    if ($st->num_rows > 0) { $st->close(); backWithError("Já existe um professor com esse telefone."); }
    $st->close();

    if ($id > 0) {
        // UPDATE
        $sql = "UPDATE professores
                SET nome = ?, formacao = ?, telefone = ?, email = ?, cursos_complementares = ?, foto = ?
                WHERE id_professor = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ssssssi", $nomeProfessor, $formacao, $telefone, $email, $cursosComp, $fotoFinal, $id);
    } else {
        // INSERT
        $sql = "INSERT INTO professores (nome, formacao, telefone, email, cursos_complementares, foto)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ssssss", $nomeProfessor, $formacao, $telefone, $email, $cursosComp, $fotoFinal);
    }

    if ($stmt->execute()) {
        header('Location: ../Paginas/professores.php');
        exit;
    } else {
        backWithError("Erro ao salvar professor.");
    }

    $stmt->close();
    $conexao->close();
}
?>