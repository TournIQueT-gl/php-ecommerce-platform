# Publish a NEW public GitHub repository. Create the empty repository first:
# https://github.com/new?name=php-ecommerce-platform
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw 'Git is not installed. Install Git for Windows first.' }
if (Test-Path -LiteralPath '.git') { throw 'This directory already contains a Git repository. Review it and push manually; no force-push will be attempted.' }
$remote = 'https://github.com/TournIQueT-gl/php-ecommerce-platform.git'
Write-Host 'Initializing a NEW Git repository for the community-alpha release...'
git init -b main
if ($LASTEXITCODE -ne 0) { throw 'Git initialization failed.' }
git add .
if ($LASTEXITCODE -ne 0) { throw 'Git staging failed.' }
$tracked = @(git ls-files)
$unsafe = @($tracked | Where-Object {
    $_ -match '(^|/)(database\.local\.php|\.env|node_modules|vendor)(/|$)' -or
    $_ -match '^storage/(cache|logs|backups|support)/[^.]' -or
    $_ -match '\.(pem|pfx|key|sql\.gz)$'
})
if ($unsafe.Count -gt 0) { throw ('Unsafe files staged; aborting. Review: ' + ($unsafe -join ', ')) }
git diff --cached --check
if ($LASTEXITCODE -ne 0) { throw 'Whitespace/patch check failed.' }
Write-Host ('Source files staged: ' + $tracked.Count)
Write-Host 'Please confirm there are NO private customer uploads, credentials, dumps or licensed assets staged.'
Write-Host 'The GitHub repository must exist and be EMPTY before continuing.'
git commit -m 'Initial Universal Commerce community alpha release'
if ($LASTEXITCODE -ne 0) { throw 'Git commit failed. You may need to configure git user.name and user.email.' }
git remote add origin $remote
if ($LASTEXITCODE -ne 0) { throw 'Adding GitHub remote failed.' }
git push -u origin main
if ($LASTEXITCODE -ne 0) { throw 'Push failed. Confirm that the GitHub repository exists, you are authenticated and it is empty.' }
Write-Host 'Published: https://github.com/TournIQueT-gl/php-ecommerce-platform'
