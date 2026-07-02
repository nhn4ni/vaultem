<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM - Size Guide</title>
    <link rel="icon" type="image/x-icon" href="vaultemLogo.ico">
    <style>
        * {
            font-family: 'Inter', sans-serif;
            box-sizing: border-box;
        }

        body {
            background-color: #E8E9DE;
            margin: 0;
            padding: 40px;
            color: #241253;
        }

        .wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #7a6e93;
            font-size: 0.95rem;
            margin-bottom: 35px;
        }

        /* ── Declaration box ───────────────────────────────── */
        .declarationBox {
            background-color: #241253;
            color: #E8E9DE;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 40px;
            border: 2px solid #bb3d3d;
        }

        .declarationBox h2 {
            margin-top: 0;
            font-size: 1.1rem;
            color: #ff8080;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .declarationBox p {
            font-size: 0.92rem;
            line-height: 1.6;
            margin: 10px 0;
        }

        .declarationBox strong {
            color: #ffb3b3;
        }

        /* ── Size tables ───────────────────────────────────── */
        .sizeSection {
            margin-bottom: 35px;
        }

        .sizeSection h2 {
            font-size: 1.2rem;
            margin-bottom: 12px;
            border-bottom: 2px solid #241253;
            padding-bottom: 6px;
        }

        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
        }

        th:nth-child(1), td:nth-child(1) { width: 25%; }
        th:nth-child(2), td:nth-child(2) { width: 40%; }
        th:nth-child(3), td:nth-child(3) { width: 35%; }

        th, td {
            text-align: left;
            padding: 12px 18px;
            font-size: 0.9rem;
        }

        th {
            background-color: #241253;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f1f0ea;
        }

        .measureNote {
            font-size: 0.8rem;
            color: #7a6e93;
            margin-top: 8px;
        }

        /* ── How to measure ────────────────────────────────── */
        .howToMeasure {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 40px;
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .howToMeasure h2 {
            font-size: 1.1rem;
            margin-top: 0;
        }

        /* ── Back button ───────────────────────────────────── */
        .backBtnWrap {
            text-align: center;
            margin-top: 30px;
        }

        #closeBtn {
            background-color: #241253;
            color: #E8E9DE;
            border: none;
            border-radius: 25px;
            padding: 12px 45px;
            font-size: 1.05rem;
            font-weight: bold;
            cursor: pointer;
        }

        #closeBtn:hover {
            background-color: #3a1e7a;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>Size Guide</h1>
        <p class="subtitle">Use this guide to accurately classify your item before booking.</p>

        <div class="declarationBox">
            <h2>&#9888; Strict Declaration - Please Read Carefully</h2>
            <p>
                By proceeding with your booking, you <strong>declare that the size category you select
                (Small / Medium / Large) accurately reflects the true measurement of your item</strong>,
                as defined in the tables below.
            </p>
            <p>
                Staff will inspect and measure your item(s) upon drop-off and/or pickup. If the item you
                present <strong>does not match the size declared</strong> during booking, this will be
                treated as a <strong>false declaration</strong> and the following will apply:
            </p>
            <p>
                &bull; A <strong>penalty fee</strong> will be charged based on the correct size category.<br>
                &bull; Repeated false declarations may result in <strong>suspension</strong> of your booking privileges.<br>
                &bull; Staff decisions on size classification at the point of inspection are <strong>final</strong>.
            </p>
            <p>
                Please measure your item honestly before selecting a category. When in doubt, round up
                to the next size.
            </p>
        </div>

        <div class="howToMeasure">
            <h2>How to Measure</h2>
            Measure the <strong>longest single dimension</strong> of your item (length, height, or
            diagonal — whichever is greatest) while it is packed and ready for storage. Use a measuring
            tape and round up to the nearest centimetre.
        </div>

        <div class="sizeSection">
            <h2>Bag</h2>
            <table>
                <tr><th>Size</th><th>Measurement</th><th>Price</th></tr>
                <tr><td>Small</td><td>Up to 30 cm</td><td>RM 3.00</td></tr>
                <tr><td>Medium</td><td>31 cm - 50 cm</td><td>RM 5.00</td></tr>
                <tr><td>Large</td><td>Above 50 cm</td><td>RM 7.00</td></tr>
            </table>
        </div>

        <div class="sizeSection">
            <h2>Luggage</h2>
            <table>
                <tr><th>Size</th><th>Measurement</th><th>Price</th></tr>
                <tr><td>Small</td><td>Up to 35 cm</td><td>RM 6.00</td></tr>
                <tr><td>Medium</td><td>36 cm - 60 cm</td><td>RM 8.00</td></tr>
                <tr><td>Large</td><td>Above 60 cm</td><td>RM 10.00</td></tr>
            </table>
        </div>

        <div class="sizeSection">
            <h2>Box</h2>
            <table>
                <tr><th>Size</th><th>Measurement</th><th>Price</th></tr>
                <tr><td>Small</td><td>Up to 40 cm</td><td>RM 2.00</td></tr>
                <tr><td>Medium</td><td>41 cm - 80 cm</td><td>RM 3.00</td></tr>
                <tr><td>Large</td><td>Above 80 cm</td><td>RM 5.00</td></tr>
            </table>
            <p class="measureNote">Note: Bucket/Pail and Others are charged as flat-rate items and are not subject to size tiers.</p>
        </div>

        <div class="backBtnWrap">
            <button id="closeBtn" onclick="window.location.href='form.php';">Back to Booking Form</button>
        </div>
    </div>
</body>
</html>