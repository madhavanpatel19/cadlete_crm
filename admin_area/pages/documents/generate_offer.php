<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// 1. If form is submitted via POST (New Offer Creation)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {
    $name          = mysqli_real_escape_string($con, $_POST['name']);
    $number        = mysqli_real_escape_string($con, $_POST['number']);
    $email         = mysqli_real_escape_string($con, $_POST['email']);
    $position      = mysqli_real_escape_string($con, $_POST['position']);
    $salary        = mysqli_real_escape_string($con, $_POST['salary']);
    $start_date    = mysqli_real_escape_string($con, $_POST['start_date']);
    $notice_period = mysqli_real_escape_string($con, $_POST['notice_period']);

    $insert_query = "INSERT INTO offer_letters (name, number, email, position, salary, start_date, notice_period)
                     VALUES ('$name', '$number', '$email', '$position', '$salary', '$start_date', '$notice_period')";
    $run_insert = mysqli_query($con, $insert_query);

    if (!$run_insert) {
        die("Error saving offer letter to database: " . mysqli_error($con));
    }

    $new_id = mysqli_insert_id($con);
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $new_id);
    exit();
}
// 2. If viewing existing offer letter
elseif (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    $get_offer = "SELECT * FROM offer_letters WHERE id='$id'";
    $run_offer = mysqli_query($con, $get_offer);
    $row_offer = mysqli_fetch_array($run_offer);

    if ($row_offer) {
        $name          = $row_offer['name'];
        $number        = $row_offer['number'];
        $email         = $row_offer['email'];
        $position      = $row_offer['position'];
        $salary        = $row_offer['salary'];
        $start_date    = $row_offer['start_date'];
        $notice_period = $row_offer['notice_period'];
    } else {
        die("Offer record not found.");
    }
} else {
    header("Location: ../../index.php?view_offer_letters");
    exit();
}

$date                  = date("d-m-Y");
$annual_salary         = $salary * 12;
$formatted_salary      = number_format($salary);
$lpa_value             = $annual_salary / 100000;
$formatted_annual      = rtrim(rtrim(number_format($lpa_value, 2, '.', ''), '0'), '.');
$formatted_start       = date("d-m-Y", strtotime($start_date));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Letter - <?php echo htmlspecialchars($name); ?></title>
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
            min-height: 297mm;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10);
            padding: 0 0 100px;
        }

        /* ===== HEADER ===== */
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

        /* ===== TITLE ===== */
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
            margin: 0 50px 30px;
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

        /* ===== META ===== */
        .meta {
            display: flex;
            justify-content: space-between;
            padding: 0 52px;
            margin-bottom: 22px;
            gap: 20px;
        }

        .meta .left {
            flex: 1;
        }

        .meta .row {
            display: flex;
            gap: 18px;
            margin-bottom: 8px;
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

        .meta .date-right {
            min-width: 170px;
            text-align: right;
            font-size: 16px;
            font-weight: 500;
            color: #555;
            padding-top: 2px;
        }

        /* ===== CONTENT ===== */
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

        .section-title {
            font-weight: 700;
            font-size: 16px;
            color: #222;
            margin: 18px 0 8px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .details-list {
            list-style: none;
            padding-left: 0;
            margin: 0 0 14px;
        }

        .details-list li {
            font-size: 16px;
            color: #555;
            margin-bottom: 6px;
            display: flex;
            gap: 8px;
        }

        .details-list li::before {
            content: "•";
            color: var(--red);
            font-weight: 800;
            flex-shrink: 0;
        }

        /* ===== SIGNATURE ===== */
        .signature-area {
            padding: 10px 52px 30px;
            margin-top: 0;
        }

        .signature-area .sincerely {
            margin-bottom: 12px;
            font-size: 14px;
            color: #333;
        }

        .sign-wrap {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .sign-image {
            width: 120px;
            height: auto;
            object-fit: contain;
            margin-left: -5px;
        }

        .sign-text .name {
            font-weight: 800;
            color: #333;
            font-size: 15px;
        }

        .sign-text .role,
        .sign-text .company {
            font-size: 13px;
            color: #444;
        }

        /* ===== FOOTER ===== */
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            padding-left: 40px;
            /* Offset for slant */
        }

        /* ===== ACTIONS ===== */
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
            font-family: 'Montserrat', sans-serif;
        }

        .btn-print {
            background: #dd2127;
            color: #fff;
            border: none;
        }

        .btn-back {
            background: #fff;
            border: 1px solid #ddd;
            color: #333;
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
                overflow: hidden;
            }

            .actions {
                display: none !important;
            }

            /* Slight reductions to ensure content fits */
            .content {
                font-size: 16px;
                line-height: 1.6;
            }

            .content p {
                margin: 0 0 12px;
            }

            .title-underline {
                margin-bottom: 20px;
            }

            .meta {
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>

    <div class="actions no-print">
        <button onclick="window.print()" class="btn-premium-add">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
        <a href="../../index.php?view_offer_letters" class="btn-premium-cancel">Back</a>
    </div>

    <div class="page-wrap">
        <div class="letter-sheet">

            <!-- HEADER -->
            <div class="top-shape">
                <div class="black-bar"></div>
                <div class="red-bar"></div>
            </div>

            <div class="brand-row">
                <img src="../../images/Cadlete_logo Landscape.png" alt="CADLETE DESIGNS Logo">
            </div>

            <!-- TITLE -->
            <div class="title">OFFER LETTER</div>
            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>

            <!-- META -->
            <div class="meta">
                <div class="left">
                    <div class="row">
                        <div class="label">Candidate Name</div>
                        <div class="value"><?php echo htmlspecialchars($name); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Position</div>
                        <div class="value"><?php echo htmlspecialchars($position); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Contact</div>
                        <div class="value"><?php echo htmlspecialchars($number); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Email</div>
                        <div class="value"><?php echo htmlspecialchars($email); ?></div>
                    </div>

                </div>
                <div class="date-right"><?php echo htmlspecialchars($date); ?></div>
            </div>

            <!-- CONTENT -->
            <div class="content">
                <div class="salutation">Dear <?php echo htmlspecialchars(explode(' ', trim($name))[0]); ?>,</div>

                <p>
                    We are pleased to offer you the position of <strong><?php echo htmlspecialchars($position); ?></strong>
                    at <strong>CADLETE DESIGNS</strong>. We are confident that your skills and experience will be
                    an excellent addition to our team.
                </p>

                <div class="section-title">Position Details:</div>
                <ul class="details-list">
                    <li><strong>Position:</strong>&nbsp;<?php echo htmlspecialchars($position); ?></li>
                    <li><strong>Salary:</strong>&nbsp;₹<?php echo $formatted_salary; ?> Per Month (In Hand) (₹<?php echo $formatted_annual; ?> LPA)</li>
                    <li><strong>Working Hours:</strong>&nbsp;Monday - Saturday, 10:00 AM - 7:00 PM</li>
                    <li><strong>Start Date:</strong>&nbsp;<?php echo htmlspecialchars($formatted_start); ?></li>
                    <li><strong>Notice Period:</strong>&nbsp;<?php echo htmlspecialchars($notice_period); ?></li>
                </ul>
                </ul>

                <p>
                    We believe that you will thrive in this role and contribute significantly to the success of
                    <strong>CADLETE DESIGNS</strong>.<br>
                    <br>

                    Please confirm your acceptance of this offer by replying to this email. If you have any
                    questions or need further information, please do not hesitate to contact us.
                </p>
                <p>
                    We look forward to welcoming you to our team.
                </p>
            </div>
            <br>

            <!-- SIGNATURE -->
            <div class="signature-area">
                <div class="sincerely">Sincerely,</div>
                <div class="sign-wrap">
                    <img src="../../images/logo_sign.png" alt="Signature" class="sign-image">
                    <div class="sign-text">
                        <div class="name">Smit Ramani</div>
                        <div class="role">Founder &amp; CEO</div>
                        <div class="company">CADLETE DESIGNS</div>
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
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

    <?php if (isset($_GET['action']) && $_GET['action'] == 'download'): ?>
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