<?php
// ---------------------------------------------------------------
// Kara David's Wish Note - single-file PHP web app (QAT test app)
// ---------------------------------------------------------------

// Local MariaDB/MySQL connection (development only: no password!)
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "birthday_wishnote";

$conn = new mysqli($servername, $username, $password, $dbname);
$message = "";
$messageType = "";

if ($conn->connect_error) {
    $message = "Database connection failed: " . htmlspecialchars($conn->connect_error);
    $messageType = "error";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$conn->connect_error) {
    // Capture all four variables from the form
    $FirstName   = trim($_POST["FirstName"]   ?? "");
    $LastName    = trim($_POST["LastName"]    ?? "");
    $GovPosition = trim($_POST["GovPosition"] ?? "");
    $Location    = trim($_POST["Location"]    ?? "");

    // =============================================================
    // INTENTIONAL FLAW (for QA testing purposes):
    // $Location is captured above, but it is deliberately OMITTED
    // from both the INSERT statement and bind_param() below.
    // Only FirstName, LastName and GovPosition are saved, so the
    // Location value silently never reaches the database.
    // A tester should catch this when comparing the form input
    // against the contents of main_corrupt_politicians.
    // =============================================================
    $sql  = "INSERT INTO main_corrupt_politicians (FirstName, LastName, GovPosition) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        // Fallback error message if the query fails to prepare
        $message = "Error: the query could not be prepared. Please try again later.";
        $messageType = "error";
    } else {
        $stmt->bind_param("sss", $FirstName, $LastName, $GovPosition); // no $Location here (flaw)
        if ($stmt->execute()) {
            $message = "Success! The wish note has been sent.";
            $messageType = "success";
        } else {
            $message = "Error: the wish note could not be saved.";
            $messageType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kara David's Wish Note</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
    background: #f0f2f5; font-family: "Segoe UI", Roboto, Arial, sans-serif; padding: 16px;
  }
  .card {
    background: #fff; width: 100%; max-width: 460px; padding: 32px 28px;
    border-radius: 10px; box-shadow: 0 4px 16px rgba(0,0,0,.12);
  }
  h2 { margin: 0 0 20px; text-align: center; color: #1e88e5; }
  label { display: block; margin: 14px 0 6px; font-weight: 600; color: #333; font-size: 14px; }
  input[type="text"] {
    width: 100%; padding: 10px 12px; border: 1px solid #cfd8dc; border-radius: 6px;
    font-size: 15px; background: #fafbfc;
  }
  input[type="text"]:focus { outline: none; border-color: #1e88e5; background: #fff; }
  .btn { border: none; border-radius: 6px; padding: 11px 18px; font-size: 15px; cursor: pointer; transition: background .2s; }
  .btn-primary { background: #1e88e5; color: #fff; width: 100%; margin-top: 22px; }
  .btn-primary:hover { background: #1565c0; }
  .btn-cancel { background: #eceff1; color: #37474f; }
  .btn-cancel:hover { background: #cfd8dc; }
  .alert { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
  .alert.success { background: #e3f2fd; color: #0d47a1; border-left: 4px solid #1e88e5; }
  .alert.error   { background: #ffebee; color: #b71c1c; border-left: 4px solid #e53935; }
  .overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    align-items: center; justify-content: center; padding: 16px; z-index: 10;
  }
  .overlay.show { display: flex; }
  .modal { background: #fff; width: 100%; max-width: 400px; border-radius: 10px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,.25); }
  .modal h3 { margin: 0 0 14px; color: #1e88e5; }
  .modal p { margin: 6px 0; font-size: 14px; color: #333; word-break: break-word; }
  .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
  .modal .btn-primary { width: auto; margin-top: 0; }
</style>
</head>
<body>
<div class="card">
  <h2>Kara David's Wish Note</h2>

  <?php if ($message !== ""): ?>
    <div class="alert <?= $messageType ?>"><?= $message ?></div>
  <?php endif; ?>

  <form id="wishForm" method="POST" action="">
    <label for="FirstName">Target's First Name</label>
    <input type="text" id="FirstName" name="FirstName" maxlength="30" required>

    <label for="LastName">Target's Last Name</label>
    <input type="text" id="LastName" name="LastName" maxlength="30" required>

    <label for="GovPosition">Target's Government Position</label>
    <input type="text" id="GovPosition" name="GovPosition" maxlength="20" required>

    <label for="Location">Target's Location</label>
    <input type="text" id="Location" name="Location" maxlength="40">

    <button type="submit" class="btn btn-primary">Send Wish Note</button>
  </form>
</div>

<div class="overlay" id="overlay">
  <div class="modal">
    <h3>Verify Target Data</h3>
    <p><strong>First Name:</strong> <span id="mFirst"></span></p>
    <p><strong>Last Name:</strong> <span id="mLast"></span></p>
    <p><strong>Government Position:</strong> <span id="mPos"></span></p>
    <p><strong>Location:</strong> <span id="mLoc"></span></p>
    <div class="modal-actions">
      <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
      <button type="button" class="btn btn-primary" id="confirmBtn">Confirm &amp; Send</button>
    </div>
  </div>
</div>

<script>
  const form = document.getElementById("wishForm");
  const overlay = document.getElementById("overlay");

  form.addEventListener("submit", function (e) {
    e.preventDefault(); // intercept submission and show the verification modal
    document.getElementById("mFirst").textContent = form.FirstName.value;
    document.getElementById("mLast").textContent  = form.LastName.value;
    document.getElementById("mPos").textContent   = form.GovPosition.value;
    document.getElementById("mLoc").textContent   = form.Location.value || "(none)";
    overlay.classList.add("show");
  });

  document.getElementById("cancelBtn").addEventListener("click", function () {
    overlay.classList.remove("show");
  });

  document.getElementById("confirmBtn").addEventListener("click", function () {
    form.submit(); // calling submit() directly bypasses the submit-event interception
  });
</script>
</body>
</html>
