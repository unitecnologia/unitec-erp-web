$cssPath = 'c:\Projetos\unitec-erp-web\public\css\erp-produtos-form.css'
$content = [System.IO.File]::ReadAllText($cssPath)

$old = @'
.erp-lookup-modal__grid-head {
    background: #eef4fb;
    cursor: pointer;
    user-select: none;
    font-weight: 700;
    font-size: 0.72rem;
    color: #334155;
}

.erp-lookup-modal__grid-head--active {
    background: #dbe7f6;
    color: #1e5a9e;
}
'@

$new = @'
.erp-lookup-modal__grid-head {
    background: #eef4fb;
    cursor: pointer;
    user-select: none;
    font-weight: 700;
    font-size: 0.72rem;
    color: #334155;
}

/* Lista auxiliar compacta (Grupo / Marca / Unidade) */
.erp-lookup-modal--compact .erp-lookup-modal__window {
    width: min(22rem, calc(100vw - 1.5rem));
}

.erp-lookup-modal--compact .erp-lookup-modal__body {
    padding: 0.3rem 0.35rem 0.2rem;
}

.erp-lookup-modal--compact .erp-lookup-modal__search-box {
    padding: 0.08rem 0.3rem 0.2rem;
}

.erp-lookup-modal--compact .erp-lookup-modal__search-input {
    min-height: 1.5rem !important;
    height: 1.5rem !important;
    padding: 0.1rem 0.35rem !important;
    font-size: 0.76rem !important;
}

.erp-lookup-modal--compact .erp-lookup-modal__grid-wrap {
    max-height: 16.5rem;
}

.erp-lookup-modal--compact .erp-lookup-modal__grid {
    font-size: 0.74rem;
    font-weight: 500;
}

.erp-lookup-modal--compact .erp-lookup-modal__grid th,
.erp-lookup-modal--compact .erp-lookup-modal__grid td {
    padding: 0.06rem 0.4rem;
    line-height: 1.15;
    height: 1.3rem;
    max-height: 1.3rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    vertical-align: middle;
}

.erp-lookup-modal--compact .erp-lookup-modal__grid-head {
    font-size: 0.68rem;
    padding: 0.1rem 0.4rem;
    height: 1.35rem;
}

.erp-lookup-modal--compact .erp-lookup-modal__empty {
    padding: 0.4rem;
    font-size: 0.74rem;
}

.erp-lookup-modal--compact .erp-lookup-modal__actions {
    padding: 0.28rem 0.35rem 0.32rem !important;
    gap: 0.25rem !important;
}

.erp-lookup-modal--compact .erp-lookup-modal__actions.erp-pcad-actions .erp-pcad-actions__btn {
    min-width: 4.8rem !important;
    height: 1.7rem !important;
}

.erp-lookup-modal--grupo .erp-lookup-modal__window {
    width: min(20rem, calc(100vw - 1.5rem));
}

.erp-lookup-modal__grid-head--active {
    background: #dbe7f6;
    color: #1e5a9e;
}
'@

if ($content.Contains('erp-lookup-modal--compact')) {
    Write-Output 'SKIP: compact styles already present'
    exit 0
}

if (-not $content.Contains($old)) {
    Write-Output 'FAIL: pattern not found'
    exit 1
}

$content = $content.Replace($old, $new)
[System.IO.File]::WriteAllText($cssPath, $content)
Write-Output 'OK: CSS updated'
