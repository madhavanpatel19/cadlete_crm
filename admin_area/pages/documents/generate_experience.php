<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name         = mysqli_real_escape_string($con, $_POST['name']);
    $email        = mysqli_real_escape_string($con, $_POST['email']);
    $number       = mysqli_real_escape_string($con, $_POST['number']);
    $designation   = mysqli_real_escape_string($con, $_POST['designation']);
    $join_date    = mysqli_real_escape_string($con, $_POST['join_date']);
    $relieve_date = mysqli_real_escape_string($con, $_POST['relieve_date']);

    $insert_query = "INSERT INTO experience_letters (name, email, number, designation, join_date, relieve_date)
                     VALUES ('$name', '$email', '$number', '$designation', '$join_date', '$relieve_date')";
    $run_insert = mysqli_query($con, $insert_query);

    if (!$run_insert) {
        die("Error saving experience letter to database: " . mysqli_error($con));
    }

    $new_id = mysqli_insert_id($con);
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $new_id);
    exit();
} elseif (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    $get_exp = "SELECT * FROM experience_letters WHERE id='$id'";
    $run_exp = mysqli_query($con, $get_exp);
    $row_exp = mysqli_fetch_array($run_exp);

    if ($row_exp) {
        $name         = $row_exp['name'];
        $email        = $row_exp['email'];
        $number       = $row_exp['number'];
        $designation   = $row_exp['designation'];
        $join_date    = $row_exp['join_date'];
        $relieve_date = $row_exp['relieve_date'];
    } else {
        die("Experience record not found.");
    }
} else {
    header("Location: ../../index.php?view_experience_letters");
    exit();
}

$current_date    = date("d-m-Y");
$formatted_join  = date("d-m-Y", strtotime($join_date));
$formatted_rel   = date("d-m-Y", strtotime($relieve_date));
$employee_id     = isset($row_exp['employee_id']) && $row_exp['employee_id'] !== '' ? $row_exp['employee_id'] : 'CD011';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experience & Relieving Letter - <?php echo htmlspecialchars($name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../../css/style.css" rel="stylesheet">

    <style>
        :root {
            --red: #e31e24;
            --dark: #222;
            --muted: #666;
            --light: #f6f6f6;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            background: #ececec;
            font-family: 'Montserrat', sans-serif;
            color: #333;
        }

        @page {
            size: A4;
            margin: 0;
        }

        .page-wrap {
            width: 100%;
            margin: 20px auto;
            padding: 0;
            display: flex;
            justify-content: center;
        }

        .letter-sheet {
            background: #fff;
            width: 210mm;
            height: 297mm;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10);
            padding: 0;
        }

        .top-shape {
            height: 80px;
            position: relative;
            background: transparent;
        }

        .top-shape .black-bar {
            width: 65%;
            height: 45px;
            background: #222;
            clip-path: polygon(0 0, 100% 0, 92% 100%, 0 100%);
        }

        .top-shape .red-bar {
            width: 50%;
            height: 12px;
            background: var(--red);
            margin-top: 12px;
            clip-path: polygon(0 0, 100% 0, 96% 100%, 0 100%);
        }

        .brand-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 52px;
            margin-top: -65px;
        }

        .brand-row img {
            height: 85px;
            object-fit: contain;
        }

        .title {
            text-align: center;
            color: var(--red);
            font-weight: 800;
            font-size: 24px;
            letter-spacing: .5px;
            margin: 6px 0 8px;
            text-transform: uppercase;
        }

        .title-underline {
            display: flex;
            align-items: center;
            margin: 0 50px 34px;
            height: 6px;
        }

        .title-underline .left {
            width: 44%;
            height: 4px;
            background: #121212;
        }

        .title-underline .right {
            width: 56%;
            height: 4px;
            background: var(--red);
        }

        .meta {
            display: flex;
            justify-content: space-between;
            padding: 0 52px;
            margin-bottom: 28px;
            gap: 20px;
        }

        .meta .left {
            flex: 1;
        }

        .meta .row {
            display: flex;
            gap: 18px;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .meta .label {
            min-width: 150px;
            font-weight: 700;
            color: #444;
            font-size: 15px;
        }

        .meta .value {
            font-size: 16px;
            color: #555;
            font-weight: 500;
        }

        .meta .date {
            min-width: 170px;
            text-align: right;
            font-size: 16px;
            font-weight: 500;
            color: #555;
            padding-top: 2px;
        }

        .content {
            padding: 0 52px;
            font-size: 17px;
            line-height: 1.8;
            color: #555;
        }

        .content .salutation {
            font-size: 20px;
            font-weight: 800;
            color: var(--red);
            margin: 10px 0 18px;
        }

        .content p {
            margin: 0 0 16px;
            text-align: left;
            font-size: 16px;
        }

        .content strong {
            color: #333;
            font-weight: 600;
        }

        .detail-block {
            margin: 14px 0 18px;
        }

        .detail-block .item {
            margin: 2px 0;
            font-size: 16px;
        }

        .signature-area {
            padding: 20px 52px 40px;
            margin-top: 5px;
        }

        .signature-area .sincerely {
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }

        .sign-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            margin-top: 10px;
            /* Added to push signature down from 'Sincerely,' */
        }

        .sign-image {
            width: 120px;
            height: auto;
            object-fit: contain;
            margin-left: -5px;
        }

        .sign-text {
            line-height: 1.45;
        }

        .sign-text .name {
            font-weight: 800;
            color: #333;
            font-size: 16px;
        }

        .sign-text .role,
        .sign-text .company {
            font-size: 14px;
            color: #444;
        }

        .footer-bar {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #d1d1d1;
            padding: 15px 52px;
        }

        .footer-inner {
            display: flex;
            justify-content: flex-start;
            gap: 40px;
            flex-wrap: nowrap;
            font-size: 13px;
            color: #222;
            font-weight: 600;
            padding-right: 160px;
            /* Leave space for corner red */
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-item i {
            color: #222;
            width: 18px;
            text-align: center;
            font-size: 16px;
        }

        .corner-red {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 180px;
            height: 80px;
            background: var(--red);
            clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
            z-index: 10;
        }

        .actions {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 999;
            display: flex;
            gap: 10px;
        }

        .actions .btn {
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 700;
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }


        @media print {
            body {
                background: #fff;
            }

            .page-wrap {
                margin: 0;
                padding: 0;
                width: 100%;
                display: block;
            }

            .letter-sheet {
                box-shadow: none;
                width: 210mm;
                height: 297mm;
                margin: 0 auto;
            }

            .actions {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="actions no-print">
        <button onclick="window.print()" class="btn-premium-add">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
        <a href="../../index.php?view_experience_letters" class="btn-premium-cancel">Back</a>
    </div>

    <div class="page-wrap">
        <div class="letter-sheet">

            <div class="top-shape">
                <div class="black-bar"></div>
                <div class="red-bar"></div>
            </div>

            <div class="brand-row">
                <img src="../../images/Cadlete_logo Landscape.png" alt="CADLETE DESIGNS Logo">
            </div>

            <div class="title">EXPERIENCE LETTER</div>

            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>

            <div class="meta">
                <div class="left">
                    <div class="row">
                        <div class="label">Employee Name</div>
                        <div class="value"><?php echo htmlspecialchars($name); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Employee ID</div>
                        <div class="value"><?php echo htmlspecialchars($employee_id); ?></div>
                    </div>
                </div>
                <div class="date"><?php echo htmlspecialchars($current_date); ?></div>
            </div>

            <div class="content">
                <div class="salutation">To Whom It May Concern,</div>

                <p>
                    This is to certify that <strong><?php echo htmlspecialchars($name); ?></strong>
                    was employed with <strong>CADLETE Designs</strong> As a
                    <strong><?php echo htmlspecialchars($designation); ?></strong>
                    from
                    <strong><?php echo htmlspecialchars($formatted_join); ?></strong> to
                    <strong><?php echo htmlspecialchars($formatted_rel); ?></strong>.
                </p>
                <p>
                    During the course of employment, she performed the assigned responsibilities with
                    dedication, professionalism, and a positive attitude. She consistently demonstrated a
                    willingness to learn, adapt, and contribute effectively as a valuable member of the
                    team.
                </p>
                <p>
                    We found her to be sincere, responsible, and committed to delivering quality work.
                    Her conduct and relationship with colleagues and management remained
                    professional throughout the tenure.
                </p>
                <p>
                    We appreciate her contributions to CADLETE Designs and thank her for the services
                    rendered. We wish her every success and prosperity in all future professional
                    endeavors.
                </p>
            </div>
            </br>
            </br>
            </br>
            <div class="signature-area">
                <div class="sincerely">Sincerely,</div>
                <div class="sign-wrap">
                    <img src="../../images/logo_sign.png" alt="Signature" class="sign-image">
                    <div class="sign-text">
                        <div class="name">Smit Ramani</div>
                        <div class="role">CEO & Chief Design Engineer</div>
                        <div class="company">CADLETE DESIGNS</div>
                    </div>
                </div>
            </div>

            <div class="footer-bar">
                <div class="footer-inner">
                    <div class="footer-col">
                        <div class="footer-item"><i class="fa fa-phone"></i>091 83202 11773
                        </div>
                        <div class="footer-item"><i class="fa fa-envelope"></i> info@cadletedesigns.com</div>
                    </div>
                    <div class="footer-col">
                        <div class="footer-item"><i class="fa-solid fa-location-dot"></i> A-106, Sun South Street, Ahmedabad</div>
                        <div class="footer-item"><i class="fa fa-globe"></i> www.cadletedesigns.com</div>
                    </div>
                </div>
            </div>

            <div class="corner-red"></div>
        </div>
    </div>

    <div class="page-wrap">
        <div class="letter-sheet">

            <div class="top-shape">
                <div class="black-bar"></div>
                <div class="red-bar"></div>
            </div>

            <div class="brand-row">
                <img src="../../images/Cadlete_logo Landscape.png" alt="CADLETE DESIGNS Logo">
            </div>

            <div class="title">RELIEVING LETTER</div>

            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>

            <div class="meta">
                <div class="left">
                    <!-- <div class="row">
                        <div class="label">Employee Name</div>
                        <div class="value"><?php echo htmlspecialchars($name); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Employee ID</div>
                        <div class="value"><?php echo htmlspecialchars($employee_id); ?></div>
                    </div> -->
                </div>
                <div class="date"><?php echo htmlspecialchars($current_date); ?></div>
            </div>

            <div class="content">
                <p>To,</p>
                <p><strong><?php echo htmlspecialchars($name); ?></strong></p>
                <p><strong>Subject: Relieving Letter</strong></p>
                <p>Dear <?php echo htmlspecialchars($name); ?>,</p>
                <p>
                    This letter is to confirm that you have been relieved from your duties as <strong><?php echo htmlspecialchars($designation); ?></strong>
                    at <strong>CADLETE Designs</strong>, effective <?php echo htmlspecialchars($formatted_rel); ?>, following your
                    resignation and completion of the required notice period and handover formalities.
                </p>
                <p>During your tenure with the company, you fulfilled your responsibilities with
                    professionalism and dedication. We appreciate your contributions and thank you for
                    your services.
                </p>
                <p>
                    We wish you continued success and all the very best in your future career and
                    personal endeavors.
                </p>
            </div>
            </br>
            </br>
            </br>
            </br>
            </br>
            <div class="signature-area">
                <div class="sincerely">Sincerely,</div>
                <div class="sign-wrap">
                    <img src="../../images/logo_sign.png" alt="Signature" class="sign-image">
                    <div class="sign-text">
                        <div class="name">Smit Ramani</div>
                        <div class="role">CEO & Chief Design Engineer</div>
                        <div class="company">CADLETE DESIGNS</div>
                    </div>
                </div>
            </div>

            <div class="footer-bar">
                <div class="footer-inner">
                    <div class="footer-col">
                        <div class="footer-item"><i class="fa fa-phone"></i>091 83202 11773
                        </div>
                        <div class="footer-item"><i class="fa fa-envelope"></i> info@cadletedesigns.com</div>
                    </div>
                    <div class="footer-col">
                        <div class="footer-item"><i class="fa-solid fa-location-dot"></i> A-106, Sun South Street, Ahmedabad</div>
                        <div class="footer-item"><i class="fa fa-globe"></i> www.cadletedesigns.com</div>
                    </div>
                </div>
            </div>

            <div class="corner-red"></div>
        </div>
    </div>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'download'): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        </script>
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>

</html>