<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

/* ===============================
   GET DATA
================================*/

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])) {

    $name = mysqli_real_escape_string($con, $_POST['name']);
    $number = mysqli_real_escape_string($con, $_POST['number']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $position = mysqli_real_escape_string($con, $_POST['position']);
    $start_date = mysqli_real_escape_string($con, $_POST['start_date']);

    $insert_query = "INSERT INTO nda_forms
    (name, number, email, position, start_date)
    VALUES
    ('$name','$number','$email','$position','$start_date')";

    mysqli_query($con, $insert_query);
} else if (isset($_GET['id'])) {

    $id = mysqli_real_escape_string($con, $_GET['id']);

    $get_nda = "SELECT * FROM nda_forms WHERE id='$id'";
    $run_nda = mysqli_query($con, $get_nda);

    $row_nda = mysqli_fetch_array($run_nda);

    if ($row_nda) {

        $name = $row_nda['name'];
        $number = $row_nda['number'];
        $email = $row_nda['email'];
        $position = $row_nda['position'];
        $start_date = $row_nda['start_date'];
    } else {

        die("NDA record not found.");
    }
} else {

    header("Location: ../../index.php?view_nda");
    exit();
}

$date_formatted =
    date("d-m-Y", strtotime($start_date));
    $current_date = date("d-m-Y");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NDA - <?php echo htmlspecialchars($name); ?></title>
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
        }

        content strong {
            color: #333;
            font-weight: 600;
        }

        .content p {
            margin: 0 0 16px;
            text-align: left;
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
        <a href="../../index.php?view_nda" class="btn-premium-cancel">Back</a>
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

            <div class="title">NON-DISCLOSURE AGREEMENT</div>

            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>
            <div class="content">
                <p>This Non-Disclosure Agreement (the "Agreement") is made and entered into as of <strong>DATE: <?php echo htmlspecialchars($date_formatted); ?></strong>, by and between CADLETE Designs ("Disclosing Party") and <strong><?php echo htmlspecialchars($name); ?></strong> ("Receiving Party").
                </p>
                <p>
                    <strong>1. Definition of Confidential Information</strong><br>
                    For the purposes of this Agreement, "Confidential Information" shall include all data,
                    materials, products, specifications, drawings, designs, plans, trade secrets, and other
                    information disclosed or sent to the Receiving Party by the Disclosing Party.
                </p>
                <p>
                    <strong>2. Obligations of the Receiving Party</strong><br>
                    The Receiving Party agrees to:<br>
                    - Hold all Confidential Information in strict confidence and not disclose it to any third
                    parties.<br>
                    - Use the Confidential Information solely for the purpose of performing work for the
                    Disclosing Party.<br>
                    - Not include any work done for the Disclosing Party in their portfolios or share it
                    publicly in any form.
                </p>
                <p>
                    <strong>3. Non-Disclosure</strong><br>
                    The Receiving Party shall not disclose, publish, or disseminate any Confidential
                    Information to any third party without the prior written consent of the Disclosing
                    Party.
                </p>
                <p>
                    <strong>4. Non-Use</strong><br>
                    The Receiving Party agrees not to use any Confidential Information for their own use
                    or for any purpose other than to carry out discussions concerning, and the
                    undertaking of, any business relationship between the Receiving Party and the
                    Disclosing Party.
                </p>
                <p>
                    <strong>5. Return of Materials</strong><br>
                    Upon termination of the business relationship, the Receiving Party agrees to promptly
                    return all Confidential Information, including any copies, to the Disclosing Party.
                </p>
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
            <div class="corner-red">01</div>
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

            <div class="title">NON-DISCLOSURE AGREEMENT</div>

            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>
            <div class="content">
                <p>
                    <strong>6. Non-Solicitation</strong><br>
                    The Receiving Party agrees not to communicate directly with any clients of the Disclosing Party without prior written consent. Any unauthorized direct communication with clients will be considered a breach of this Agreement and may result in immediate termination of the job contract and legal action.
                </p>
                <p>
                    <strong>7. Term and Termination</strong><br>
                    This Agreement shall commence as of the date first written above and shall continue
                    in effect until terminated by either party with thirty (30) days written notice. The
                    Receiving Party's duty to hold in confidence Confidential Information that was
                    disclosed during the term shall remain in effect indefinitely.
                </p>
                <p>
                    <strong>8. Remedies</strong><br>
                    The Receiving Party acknowledges that any breach of this Agreement may cause
                    irreparable harm to the Disclosing Party. As such, the Disclosing Party shall be entitled
                    to seek equitable relief, including injunction and specific performance, in the event of
                    any breach or threatened breach of the terms of this Agreement. Such remedies shall
                    not be deemed to be the exclusive remedies for a breach of this Agreement but shall
                    be in addition to all other remedies available at law or in equity.
                </p>
                <p>
                    <strong>9. Governing Law</strong><br>
                    This Agreement shall be governed by and construed in accordance with the laws of
                    the State of [State], without regard to its conflict of laws principles.

                </p>
                <p>
                    <strong>10. Miscellaneous</strong><br>
                    - This Agreement constitutes the entire agreement between the parties and
                    supersedes all prior agreements, understandings, and communications between the
                    parties.<br>
                    - No amendment or modification of this Agreement shall be valid or binding upon the
                    parties unless made in writing and signed by both parties.<br>
                    - If any provision of this Agreement is found to be invalid or unenforceable, the
                    remaining provisions shall continue to be valid and enforceable.
                </p>
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
            <div class="corner-red">02</div>
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

            <div class="title">NON-DISCLOSURE AGREEMENT</div>

            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>
            <div class="content">
                <p><strong>IN WITNESS WHEREOF</strong>, the parties hereto have executed this Non-Disclosure
                    Agreement as of the day and year first above written.
                </p>
                <br><br>
                <div class="content" style="margin-top: 40px; font-size: 14px; color: #444;">
                    <!-- Releasor Block -->
                    <div style="position: relative; margin-bottom: 60px;">
                        <div style="display: flex; align-items: flex-end; margin-bottom: 15px;">
                            <div>Releasor's Signature</div>
                            <div style="position: relative; flex: 0 0 220px; border-bottom: 1px dashed #888; margin: 0 10px;">
                                <img src="../../images/logo_sign.png" alt="Signature" style="position: absolute; bottom: -10px; left: 10px; height: 65px; object-fit: contain;">
                            </div>
                            <div>Date</div>
                            <div style="flex: 0 0 140px; border-bottom: 1px dashed #888; margin-left: 10px;"></div>
                        </div>
                        <div>Print Name: Smit Ramani, CADLETE DESIGNS, India</div>
                    </div>
                    <br>

                    <!-- Recipient Block -->
                    <div style="position: relative; margin-bottom: 40px;">
                        <div style="display: flex; align-items: flex-end; margin-bottom: 15px;">
                            <div>Recipient's Signature</div>
                            <div style="flex: 0 0 220px; border-bottom: 1px dashed #888; margin: 0 10px;"></div>
                            <div>Date</div>
                            <div style="flex: 0 0 140px; border-bottom: 1px dashed #888; margin-left: 10px;"></div>
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <div>Print Name</div>
                            <div style="flex: 0 0 300px; border-bottom: 1px dashed #888; margin-left: 10px;"></div>
                        </div>
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
            <div class="corner-red">03</div>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>

</html>