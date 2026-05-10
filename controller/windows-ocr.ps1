param(
  [Parameter(Mandatory = $true)]
  [string]$ImagePath
)

$ErrorActionPreference = 'Stop'
$OutputEncoding = [System.Text.UTF8Encoding]::new()
[Console]::OutputEncoding = [System.Text.UTF8Encoding]::new()

if (-not (Test-Path -LiteralPath $ImagePath -PathType Leaf)) {
  throw 'Image introuvable.'
}

Add-Type -AssemblyName System.Runtime.WindowsRuntime
[void][Windows.Globalization.Language, Windows.Globalization, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.BitmapAlphaMode, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.BitmapPixelFormat, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.BitmapTransform, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.ColorManagementMode, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.ExifOrientationMode, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Graphics.Imaging.SoftwareBitmap, Windows.Graphics.Imaging, ContentType = WindowsRuntime]
[void][Windows.Media.Ocr.OcrEngine, Windows.Foundation, ContentType = WindowsRuntime]
[void][Windows.Storage.FileAccessMode, Windows.Storage, ContentType = WindowsRuntime]
[void][Windows.Storage.StorageFile, Windows.Storage, ContentType = WindowsRuntime]
[void][Windows.Storage.Streams.IRandomAccessStream, Windows.Storage.Streams, ContentType = WindowsRuntime]

$asTaskGeneric = (
  [System.WindowsRuntimeSystemExtensions].GetMethods() |
    Where-Object {
      $_.Name -eq 'AsTask' -and
      $_.GetParameters().Count -eq 1 -and
      $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
    }
)[0]

function Await-WinRtOperation($Operation, [type]$ResultType) {
  $asTask = $asTaskGeneric.MakeGenericMethod($ResultType)
  $task = $asTask.Invoke($null, @($Operation))
  try {
    $task.Wait()
  } catch {
    if ($null -ne $task.Exception -and $null -ne $task.Exception.InnerException) {
      throw $task.Exception.InnerException.Message
    }
    throw
  }
  return $task.Result
}

$file = Await-WinRtOperation ([Windows.Storage.StorageFile]::GetFileFromPathAsync($ImagePath)) ([Windows.Storage.StorageFile])
$stream = $null

try {
  $stream = Await-WinRtOperation ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
  $decoder = Await-WinRtOperation ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])

  $maxDimension = [Windows.Media.Ocr.OcrEngine]::MaxImageDimension
  $sourceMax = [Math]::Max([double]$decoder.PixelWidth, [double]$decoder.PixelHeight)
  $transform = [Windows.Graphics.Imaging.BitmapTransform]::new()

  if ($sourceMax -gt $maxDimension) {
    $scale = $maxDimension / $sourceMax
    $transform.ScaledWidth = [uint32][Math]::Max(1, [Math]::Floor($decoder.PixelWidth * $scale))
    $transform.ScaledHeight = [uint32][Math]::Max(1, [Math]::Floor($decoder.PixelHeight * $scale))
  }

  $bitmap = Await-WinRtOperation (
    $decoder.GetSoftwareBitmapAsync(
      [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8,
      [Windows.Graphics.Imaging.BitmapAlphaMode]::Premultiplied,
      $transform,
      [Windows.Graphics.Imaging.ExifOrientationMode]::RespectExifOrientation,
      [Windows.Graphics.Imaging.ColorManagementMode]::DoNotColorManage
    )
  ) ([Windows.Graphics.Imaging.SoftwareBitmap])

  $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
  if ($null -eq $engine) {
    foreach ($language in [Windows.Media.Ocr.OcrEngine]::AvailableRecognizerLanguages) {
      if ($language.LanguageTag -match '^(fr|en)') {
        $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage($language)
        break
      }
    }
  }
  if ($null -eq $engine) {
    $languages = [Windows.Media.Ocr.OcrEngine]::AvailableRecognizerLanguages
    if ($languages.Count -gt 0) {
      $engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromLanguage($languages[0])
    }
  }
  if ($null -eq $engine) {
    throw 'Moteur OCR Windows indisponible.'
  }

  $result = Await-WinRtOperation ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])
  $text = [string]$result.Text
  if ([string]::IsNullOrWhiteSpace($text)) {
    throw 'Aucun texte detecte.'
  }

  Write-Output $text
} finally {
  if ($null -ne $stream) {
    $stream.Dispose()
  }
}
