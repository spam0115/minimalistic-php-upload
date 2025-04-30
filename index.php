<?php
global $yourDomain, $uploadDirectory, $addressToReportTo, $projectDirectory;
error_reporting(0);
include("settings.php");

# see ./assets/translations for available languages (USE IEFT language codes: https://en.wikipedia.org/wiki/IETF_language_tag#List_of_common_primary_language_subtags)
$availableLanguages = array("en", "de");
$choosenLanguage = in_array(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2), $availableLanguages) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : "en";
include("assets/translations/texts_" . $choosenLanguage . ".php");

$numberOfSuccessfullUploadedFiles = 0;
$collectedFilenames = "";
$simplifiedDomainname = trim(parse_url($yourDomain, PHP_URL_HOST));

// remove illegal file system characters https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
function sanitizeFilename($name): string {
    $name = str_replace(array_merge(
        array_map('chr', range(0, 31)),
        array('<', '>', ':', '"', '/', '\\', '|', '?', '*', ' ', '-', '\'')
    ), '', $name);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    return mb_strcut(pathinfo($name, PATHINFO_FILENAME), 0, 200 - ($ext ? strlen($ext) + 1 : 0),
            mb_detect_encoding($name)) . ($ext ? '.' . $ext : '');
}

if (isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST") {
    // Loop through $_FILES to treat all files
    foreach ($_FILES['files']['name'] as $f => $filename) {
        if ($_FILES['files']['error'][$f] == 4) {
            continue; // Skip file on any error
        }
        if ($_FILES['files']['error'][$f] == 0) {
            try {
                $randomPrefix = bin2hex(random_bytes(10));
            } catch (Exception $ignore) {
                $randomPrefix = substr(hash('sha256', openssl_random_pseudo_bytes(20)), 10);
            }
            $sanitizedFilename = $randomPrefix . "_" . sanitizeFilename($filename);
            if (move_uploaded_file($_FILES["files"]["tmp_name"][$f], $uploadDirectory . $sanitizedFilename)) {
                $collectedFilenames = $collectedFilenames . "\r\n" . $sanitizedFilename . " <=> " . $yourDomain . "/" . $projectDirectory . $uploadDirectory . $sanitizedFilename;
                $numberOfSuccessfullUploadedFiles++;
            }
        }
    }
    if ($numberOfSuccessfullUploadedFiles > 0) {
        mb_internal_encoding('UTF-8');
        $encoded_subject = mb_encode_mimeheader(textNewFileUpload($simplifiedDomainname, $numberOfSuccessfullUploadedFiles), 'UTF-8', 'B', "\r\n", strlen('Subject: '));
        $header = 'From: ' . $addressToReportTo . "\r\n" . 'Reply-To: ' . $addressToReportTo . "\r\n" . 'X-Mailer: PHP/' . phpversion();
        $message = textMailMessage($numberOfSuccessfullUploadedFiles, $_SERVER['REMOTE_ADDR'], $collectedFilenames);
        mail($addressToReportTo, $encoded_subject, $message, $header);
    }
}
?>

<!doctype html>
<html lang="<?php echo $choosenLanguage; ?>">
<head>
    <meta charset="UTF-8"/>
    <META NAME="ROBOTS" CONTENT="NOINDEX,NOFOLLOW">
    <title><?php echo textTitle() . " | " . $simplifiedDomainname; ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/site.webmanifest">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">
</head>
<body>
<div class="logo" style="text-align: center;">
    <img src="assets/Circle-icons-speedometer.svg" width="150px" height="150px" alt="logo">
</div>
<div style="text-align: center;"><h1><?php echo $simplifiedDomainname ?></h1></div>
<div class="wrap">
    <h1>
        <?php
        echo textTitle() . ":</h1>";

        # show error messages if upload failed
        if (isset($message)) {
            foreach ($message as $msg) {
                printf("<p class='status'>%s</p><br />\n", $msg);
            }
        }
        # success message if upload has finished
        if ($numberOfSuccessfullUploadedFiles > 0) {
            printf("<p class='status'>%d " . textSuccessfulUploaded() . "</p>\n", $numberOfSuccessfullUploadedFiles);
        }
        ?>

        <form action="" method="post" enctype="multipart/form-data">
            <div style="display: block; margin: 20px;">
                <input type="file" name="files[]" multiple="multiple" accept="*">
            </div>
            <input type="submit" value="<?php echo textUploadButton(); ?>">
        </form>
        <div style="display: block; font-style: italic;"></div>
        <div class="infotext"><?php echo textUploadBottomLine(); ?></div>
        <div class="progress-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
</div>
<div class="footer">
    <a href="https://github.com/timluedtke/minimalistic-PHP-Upload" target="_blank">minimalistic-PHP-Upload v1.4.0<br/>
        <img src="assets/GitHub_Logo.png" alt="logo github"></a>
</div>
</body>
<script>
    const form = document.querySelector('form');
    const progressBar = document.getElementById('progressBar');
    const infotext = document.getElementById('infotext');
    const overlay = document.createElement('div');

    overlay.className = 'overlay';
    document.body.appendChild(overlay);

    form.addEventListener('submit', function (e) {
        //infotext.parentNode.removeChild(infotext);

        e.preventDefault();
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        overlay.style.display = 'block';

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentComplete + '%';
            }
        });

        xhr.addEventListener('load', function () {
            overlay.style.display = 'none';
            progressBar.textContent = '<?php echo textSuccessfulUploaded(); ?>';
        });

        xhr.addEventListener('error', function () {
            overlay.style.display = 'none';
            progressBar.textContent = 'ERROR';
        });

        xhr.open('POST', form.action);
        xhr.send(formData);
    });
</script>
</html>