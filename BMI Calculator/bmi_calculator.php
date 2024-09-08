<?php
require 'auth.php';

if (!is_logged_in()) {
    header("Location: index.php");
    exit();
}

// Fetch user ID and check if BMI user exists
$user_id = $_SESSION['user_id'];
$bmi_user_id = null;
$error = '';
$result = '';

require 'config.php';

// Check if the user has an associated BMIUser record
$stmt = $conn->prepare("SELECT BMIUserID FROM BMIUsers WHERE AppUserID = :user_id");
$stmt->execute(['user_id' => $user_id]);
$bmi_user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($bmi_user) {
    $bmi_user_id = $bmi_user['BMIUserID'];
} else {
    // If not, create one
    $stmt = $conn->prepare("INSERT INTO BMIUsers (AppUserID, Name, Age, Gender) VALUES (:user_id, :name, :age, :gender)");
    $stmt->execute([
        'user_id' => $user_id,
        'name' => $_SESSION['username'], // Default name to username
        'age' => 0, // Default age (this can be updated later)
        'gender' => 'Other' // Default gender (can be updated later)
    ]);
    $bmi_user_id = $conn->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $height = floatval($_POST['height']);
    $weight = floatval($_POST['weight']);

    if ($height > 0 && $weight > 0) {
        $bmi = $weight / (($height / 100) * ($height / 100));
        $result = "Your BMI is " . number_format($bmi, 2);

        // Save the BMI calculation to the database
        $stmt = $conn->prepare("INSERT INTO BMIRecords (BMIUserID, Height, Weight, BMI) VALUES (:bmi_user_id, :height, :weight, :bmi)");
        $stmt->execute([
            'bmi_user_id' => $bmi_user_id,
            'height' => $height,
            'weight' => $weight,
            'bmi' => $bmi
        ]);
    } else {
        $error = "Please enter valid height and weight.";
    }
}
function categorize_bmi($bmi) {
    if ($bmi < 18.5) {
        return 'Worse';
    } elseif ($bmi >= 18.5 && $bmi < 24.9) {
        return 'Good';
    } else {
        return 'Bad';
    }
}


// Fetch BMI records for the current user
$stmt = $conn->prepare("SELECT * FROM BMIRecords WHERE BMIUserID = :bmi_user_id ORDER BY RecordedAt DESC");
$stmt->execute(['bmi_user_id' => $bmi_user_id]);
$bmi_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="text-center">BMI Calculator</h2>
        <form method="POST" action="" class="needs-validation" novalidate>
            <div class="mb-3">
                <label for="height" class="form-label">Height (in cm)</label>
                <input type="number" class="form-control" id="height" name="height" required>
                <div class="invalid-feedback">Please enter your height.</div>
            </div>
            <div class="mb-3">
                <label for="weight" class="form-label">Weight (in kg)</label>
                <input type="number" class="form-control" id="weight" name="weight" required>
                <div class="invalid-feedback">Please enter your weight.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Calculate</button>
            <div class="text-danger mt-3"><?php echo $error; ?></div>
            <div class="text-success mt-3"><?php echo $result; ?></div>
        </form>

        <h3 class="mt-5">BMI Calculation History</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Height (cm)</th>
            <th scope="col">Weight (kg)</th>
            <th scope="col">BMI</th>
            <th scope="col">Category</th> <!-- New Column -->
            <th scope="col">Date</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bmi_records as $index => $record): ?>
            <tr>
                <th scope="row"><?php echo $index + 1; ?></th>
                <td><?php echo $record['Height']; ?></td>
                <td><?php echo $record['Weight']; ?></td>
                <td><?php echo number_format($record['BMI'], 2); ?></td>
                <td><?php echo categorize_bmi($record['BMI']); ?></td> <!-- New Column Data -->
                <td><?php echo date('Y-m-d H:i:s', strtotime($record['RecordedAt'])); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>


        <a href="logout.php" class="btn btn-secondary w-100 mt-3">Logout</a>
    </div>
</div>
<?php include 'footer.php'; ?>
