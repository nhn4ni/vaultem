<!DOCTYPE html>
<html lang="en">
<!-- <link rel="stylesheet" href="block.css" type="text/css"> -->

<!-- <link rel="stylesheet" href="mobile.css" type="text/css"> -->

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VaulteM</title>



</head>

<style>
    * {
        font-family: 'Courier New', Courier, monospace;
    }

    body {
        background-color: #E8E9DE;
        color: #241253;
    }

    #body2 {
        margin: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .back {
        position: absolute;
        border-style: none;
        background-color: #E8E9DE;
        font-size: 1.2rem;
        font-weight: bold;
        color: #241253;
        margin: 15px;
    }

    h2 {
        text-align: center;
        margin-top: 0;

    }

    .paymentBox {
        width: 350px;
        min-height: 55%;
        background-color: #241253;
        color: #E8E9DE;
        border: 1px solid #ccc;
        border-radius: 20px;
        padding: 20px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: flex-start;

    }


    .paymentBox h2 {
        width: 100%;
    }

    #totalAmount {
        font-weight: bold;
        font-size: 1.2rem;
    }

    #payBtn,
    #pay {
        background-color: #E8E9DE;
        width: 80%;
        padding: 10px;
        border-style: none;
        border-radius: 25px;
        /* align-self: center; */
    }

    #payBtn:hover,
    #pay:hover {
        background-color: #d6d7ca;
        cursor: pointer;
        transform: translateY(-2px);
    }


    .totalSection {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }


    .totalSection p {
        margin: 5px 0;
    }

    #payBtn {
        margin-top: 10px;
    }

    #payMethod {
        /* display: none;
        opacity:0; */
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }

    #payMethod.show {
        /* display: block; */
        opacity: 1;
        max-height: 300px;
        transform: translateY(0);
    }

    .popupOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;

        background: rgba(0, 0, 0, 0.4);
        

        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
    }

    .popupOverlay.show {
        opacity: 1;
        visibility: visible;
    }

    .popupBox {
        background: white;
        color: #241253;

        padding: 30px;
        border-radius: 20px;

        width: 320px;
        text-align: center;

        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);

    }

    .popupOverlay.show .popupBox {
        transform: scale(1);
    }

    #home {
        margin-top: 15px;
        padding: 10px 20px;

        border: none;
        border-radius: 20px;

        background: #241253;
        color: white;

        cursor: pointer;
    }
</style>

<body>

    <div id="wrapper">
        <button class="back" onclick="history.back()"> &#60; Back</button>
    </div>

    <div id="body2">
        <div class="paymentBox">
            <h2>Payment</h2>


            <div class="bookingSummary">
                <h3>Booking Summary</h3>
                <p>
                    Total Item:
                    <span id="totalItem">3</span>
                </p>
                <p>
                    Drop-off Date:
                    <span id="dropOff_Date">Loading...</span>
                </p>
                <p>
                    Pick-up Date:
                    <span id="pickUp_Date"></span>
                </p>

                <p>
                    Residential College:
                    <span id="resCollege">Lestari B</span>
                </p>
            </div>

            <div class="totalSection">
                <p>Total:</p>
                <p><span id="totalAmount">RM 1.50</span></p>
                <button id="payBtn" onclick="toggleMenu()">Select Payment Method</button>
            </div>
            <div id="payMethod">

<form action="payment_process.php" method="POST">

<p>Select your payment method:</p>

<input type="radio" id="banking"
name="payment_method"
value="Online Banking" required>

<label for="banking">Online Banking</label><br>

<input type="radio" id="card"
name="payment_method"
value="Credit/Debit Card">

<label for="card">Credit/Debit Card</label><br>

<input type="radio" id="qr"
name="payment_method"
value="QR">

<label for="qr">QR</label><br><br>

<input type="hidden"
name="amount"
value="1.50">

<input type="hidden"
name="booking_id"
value="1">

<button type="submit" id="pay">
Pay Now
</button>

</form>

</div>

        </div>

        <div id="payPopup" class="popupOverlay">
            <div class="popupBox">
                <p>Your payment of RM0.50 was successful!</p>

                <button type="button" id="home" onclick="window.location.href='mainStatus.php'">
                    Go back home
                </button>
            </div>
        </div>
    </div>


    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const retrievedDate = localStorage.getItem('savedDropOffDate');
            const pickDate = localStorage.getItem('savedpickupDate');
            document.getElementById('dropOff_Date').textContent = retrievedDate;
            document.getElementById('pickUp_Date').textContent = pickDate;

        });

        function toggleMenu() {
            document.getElementById("payMethod").classList.toggle("show");
        }

        document.getElementById("pay").addEventListener("click", function () {
            document.getElementById("payPopup").classList.add("show");
        });
    </script>
</body>

</html>