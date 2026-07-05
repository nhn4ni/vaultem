<?php

function autoCancelExpiredBookings(mysqli $conn) {
    $expiredQuery = $conn->query("
        SELECT b.Booking_ID
        FROM booking b
        LEFT JOIN payment p ON b.Booking_ID = p.Booking_ID
        WHERE LOWER(b.Booking_Status) IN ('approved', 'pending')
          AND (
                b.DropOff_Date < CURDATE()
                OR (b.DropOff_Date = CURDATE() AND CURTIME() > '11:00:00')
              )
          AND (p.Payment_Status IS NULL OR UPPER(p.Payment_Status) != 'Y')
    ");

    if (!$expiredQuery || $expiredQuery->num_rows === 0) {
        return;
    }

    $expiredIds = [];
    while ($row = $expiredQuery->fetch_assoc()) {
        $expiredIds[] = (int)$row['Booking_ID'];
    }

    foreach ($expiredIds as $bookingId) {

        // Restore reserved storage units back to storespace
        $itemsRes = $conn->query("SELECT SUM(Quantity) AS total, Space_ID FROM item WHERE Booking_ID = $bookingId GROUP BY Space_ID");
        if ($itemsRes) {
            while ($itemRow = $itemsRes->fetch_assoc()) {
                $qty     = (int)$itemRow['total'];
                $spaceId = (int)$itemRow['Space_ID'];
                if ($spaceId > 0) {
                    $restore = $conn->prepare("UPDATE storespace SET Size = Size + ? WHERE Space_ID = ?");
                    $restore->bind_param("ii", $qty, $spaceId);
                    $restore->execute();
                    $restore->close();
                }
            }
        }

        // Mark the booking as auto-cancelled due to non-payment
        $update = $conn->prepare("UPDATE booking SET Booking_Status = 'Cancelled_Unpaid' WHERE Booking_ID = ?");
        $update->bind_param("i", $bookingId);
        $update->execute();
        $update->close();
    }
}