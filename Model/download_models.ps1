# Run this script in PowerShell inside:
# C:\xampp\htdocs\CareerStrand-template\View\FrontOffice\assets\models\

$base = "https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights"

$files = @(
    "ssd_mobilenetv1_model-weights_manifest.json",
    "ssd_mobilenetv1_model-shard1",
    "face_landmark_68_model-weights_manifest.json",
    "face_landmark_68_model-shard1",
    "face_recognition_model-weights_manifest.json",
    "face_recognition_model-shard1",
    "face_recognition_model-shard2"
)

foreach ($file in $files) {
    $url = "$base/$file"
    Write-Host "Downloading $file ..."
    Invoke-WebRequest -Uri $url -OutFile $file
    Write-Host "Done: $file"
}

Write-Host ""
Write-Host "All models downloaded! Run 'dir' to verify."
