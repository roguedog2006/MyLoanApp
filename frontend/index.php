<?php
// frontend/index.php - myLoanApp landing + loan application
// - Place this file at frontend/index.php
// - Provide backend API base via environment var API_BASE_URL (default: http://localhost:8000/api)
// - PHP 7.2+ recommended

session_start();

// Configuration
$apiBase = getenv('API_BASE_URL') ?: 'http://localhost:8000/api';

// Utility: escape output
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Determine if request is AJAX (fetch/XHR) expecting JSON
function is_ajax() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strpos($accept, 'application/json') !== false) return true;
    return false;
}

// Response helper for AJAX
function json_resp($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Flash for non-AJAX flows
$flash = null;

// Handle form POST: proxy to backend API (loans apply)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    // Basic CSRF check
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        $err = 'Invalid session (CSRF token mismatch). Please refresh and try again.';
        if (is_ajax()) json_resp(['ok' => false, 'message' => $err], 400);
        $flash = ['type' => 'error', 'text' => $err];
    } else {
        // Gather & basic validate inputs
        $name   = trim($_POST['name'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $amount = floatval(str_replace(',', '', $_POST['amount'] ?? '0'));
        $term   = intval($_POST['term'] ?? 0);
        $income = floatval(str_replace(',', '', $_POST['income'] ?? '0'));

        $errors = [];
        if ($name === '') $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if ($amount <= 0) $errors[] = 'Loan amount must be greater than 0.';
        if ($term <= 0) $errors[] = 'Repayment term must be greater than 0 months.';
        if ($income <= 0) $errors[] = 'Monthly income must be greater than 0.';

        if (!empty($errors)) {
            $msg = implode(' ', $errors);
            if (is_ajax()) json_resp(['ok' => false, 'message' => $msg], 422);
            $flash = ['type' => 'error', 'text' => $msg];
        } else {
            // Prepare payload for backend
            $payload = json_encode([
                'full_name' => $name,
                'email'     => $email,
                'amount'    => $amount,
                'term_months'=> $term,
                'monthly_income' => $income,
            ]);

            // cURL proxy to backend endpoint
            $ch = curl_init(rtrim($apiBase, '/') . '/loans/apply');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $respBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($respBody === false || $curlErr) {
                $msg = 'Error contacting backend: ' . ($curlErr ?: 'unknown');
                if (is_ajax()) json_resp(['ok' => false, 'message' => $msg], 502);
                $flash = ['type' => 'error', 'text' => $msg];
            } else {
                $resp = json_decode($respBody, true);
                if ($httpCode >= 200 && $httpCode < 300) {
                    $msg = 'Application submitted successfully. We will contact you shortly.';
                    if (is_ajax()) json_resp(['ok' => true, 'message' => $msg], 200);
                    $flash = ['type' => 'success', 'text' => $msg];
                    $_POST = [];
                } else {
                    $msg = $resp['message'] ?? ('Backend error (HTTP ' . $httpCode . ')');
                    if (is_ajax()) json_resp(['ok' => false, 'message' => $msg], $httpCode ?: 500);
                    $flash = ['type' => 'error', 'text' => $msg];
                }
            }
        }
    }
}

// Helper to show POSTed value safely
function v($key, $default = '') {
    return e($_POST[$key] ?? $default);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>myLoanApp — Borrow smarter</title>
  <meta name="description" content="Apply for loans quickly and securely." />
  <style>
    :root{
      --accent:#2563eb;
      --accent-2:#0ea5a4;
      --muted:#6b7280;
      --bg:#f8fafc;
      --card:#ffffff;
      --radius:12px;
      --max:1100px;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;background:var(--bg);color:#0f172a;line-height:1.4}
    .wrap{max-width:var(--max);margin:28px auto;padding:20px}
    header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}
    .brand{display:flex;align-items:center;gap:12px}
    .logo{width:48px;height:48px;border-radius:10px;background:linear-gradient(135deg,var(--accent),#60a5fa);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:18px;box-shadow:0 6px 18px rgba(37,99,235,0.12)}
    .title{font-weight:700;font-size:18px}
    nav a{margin-left:14px;color:var(--muted);text-decoration:none;font-weight:600;font-size:14px}
    .hero{position:relative;border-radius:var(--radius);overflow:hidden;background:linear-gradient(180deg,rgba(15,23,42,0.02),rgba(15,23,42,0.01));min-height:320px;display:flex;align-items:center}
    .hero .slides{position:absolute;inset:0}
    .slide{position:absolute;inset:0;background-position:center;background-size:cover;opacity:0;transform:scale(1.02);transition:opacity 900ms ease, transform 1200ms ease}
    .slide.show{opacity:1;transform:scale(1)}
    .hero-content{position:relative;z-index:2;padding:40px;max-width:640px}
    .hero h1{margin:0 0 8px 0;font-size:32px}
    .hero p{margin:0;color:var(--muted)}
    .cta{margin-top:18px;display:flex;gap:12px}
    .btn{background:var(--accent);color:white;padding:10px 16px;border-radius:10px;border:none;cursor:pointer;font-weight:700}
    .btn.ghost{background:transparent;color:var(--accent);border:2px solid rgba(37,99,235,0.08)}
    .grid{display:grid;grid-template-columns:1fr 380px;gap:20px;margin-top:18px}
    @media(max-width:980px){.grid{grid-template-columns:1fr}.hero{min-height:260px}.hero-content{padding:22px}}
    .card{background:var(--card);border-radius:12px;padding:18px;box-shadow:0 8px 28px rgba(2,6,23,0.06)}
    label{display:block;margin-top:12px;font-size:13px;color:#0f172a}
    input[type="text"],input[type="email"],input[type="number"],select,textarea{width:100%;padding:10px;border:1px solid #e6eef6;border-radius:10px;margin-top:6px;box-sizing:border-box;font-size:14px}
    .muted{color:var(--muted)}
    .flash{padding:12px;border-radius:10px;margin-bottom:12px;font-weight:600}
    .flash.success{background:#ecfdf5;color:#065f46}
    .flash.error{background:#fff1f2;color:#7f1d1d}
    .calc-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .calc-result{background:#f1f5f9;padding:12px;border-radius:10px;margin-top:12px}
    footer{text-align:center;margin-top:26px;color:var(--muted);font-size:13px}
    .hero-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(2,6,23,0.15),rgba(2,6,23,0.02));z-index:1}
    .slide-dots{position:absolute;right:18px;bottom:18px;z-index:3;display:flex;gap:8px}
    .dot{width:10px;height:10px;border-radius:20px;background:rgba(255,255,255,0.6);cursor:pointer;box-shadow:0 2px 6px rgba(2,6,23,0.1)}
    .dot.active{background:white}
    .small{font-size:13px;color:var(--muted)}
  </style>
</head>
<body>
  <div class="wrap">
    <header>
      <div class="brand">
        <div class="logo">mL</div>
        <div>
          <div class="title">myLoanApp</div>
          <div class="small muted">Smart. Fast. Transparent.</div>
        </div>
      </div>
      <nav>
        <a href="#apply">Apply</a>
        <a href="#calculator">Calculator</a>
        <a href="#contact">Contact</a>
      </nav>
    </header>

    <?php if ($flash): ?>
      <div class="flash <?php echo $flash['type'] === 'success' ? 'success' : 'error' ?>">
        <?php echo e($flash['text']); ?>
      </div>
    <?php endif; ?>

    <section class="hero card" aria-label="Hero">
      <div class="slides" id="slides">
        <div class="slide show" style="background-image:url('https://images.unsplash.com/photo-1521790361789-a4d4b6b9c8e8?q=80&w=1600&auto=format&fit=crop');" role="img" aria-label="Professional team working"></div>
        <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1600&auto=format&fit=crop');" role="img" aria-label="Modern office workspace"></div>
        <div class="slide" style="background-image:url('https://images.unsplash.com/photo-1515165562835-c4f94e6d1d2e?q=80&w=1600&auto=format&fit=crop');" role="img" aria-label="Person reviewing documents"></div>
      </div>

      <div class="hero-overlay" aria-hidden="true"></div>

      <div class="hero-content">
        <h1>Flexible personal loans designed for you</h1>
        <p class="muted">Simple application, transparent terms, and quick decisions — get the funds you need with confidence.</p>
        <div class="cta">
          <button class="btn" onclick="document.getElementById('apply').scrollIntoView({behavior:'smooth'})">Apply now</button>
          <button class="btn ghost" onclick="document.getElementById('calculator').scrollIntoView({behavior:'smooth'})">Try calculator</button>
        </div>
      </div>

      <div class="slide-dots" id="slideDots" aria-hidden="true"></div>
    </section>

    <div class="grid" style="margin-top:18px;">
      <main>
        <div class="card" id="calculator" tabindex="-1">
          <h2 style="margin:0 0 8px 0">Loan calculator</h2>
          <p class="muted">Estimate monthly payments, total repayment, and interest for a loan.</p>

          <div style="margin-top:12px">
            <div class="calc-row">
              <label>Amount (USD)
                <input id="calc-amount" type="number" min="100" step="100" value="5000">
              </label>
              <label>Term (months)
                <input id="calc-term" type="number" min="1" step="1" value="12">
              </label>
            </div>

            <div class="calc-row">
              <label>Interest rate (annual %)
                <input id="calc-rate" type="number" min="0" step="0.01" value="9.5">
              </label>
              <label>Extra monthly payment (optional)
                <input id="calc-extra" type="number" min="0" step="0.01" value="0">
              </label>
            </div>

            <div class="calc-result" id="calcResult" aria-live="polite">
              <div><strong>Estimated monthly payment:</strong> <span id="calc-month">$0.00</span></div>
              <div style="margin-top:6px"><strong>Total payment:</strong> <span id="calc-total">$0.00</span></div>
              <div style="margin-top:6px"><strong>Total interest:</strong> <span id="calc-interest">$0.00</span></div>
            </div>
            <p class="small muted" style="margin-top:8px">This is an estimate only and not an offer. Actual rates and terms vary by applicant.</p>
          </div>
        </div>

        <div style="height:14px"></div>

        <div class="card">
          <h3 style="margin:0 0 8px 0">Why myLoanApp</h3>
          <ul class="muted" style="margin:8px 0 0 16px">
            <li>Fast online decisions</li>
            <li>Transparent fees and clear terms</li>
            <li>Secure data handling and privacy</li>
          </ul>
        </div>
      </main>

      <aside>
        <div class="card" id="apply" tabindex="-1">
          <h3 style="margin:0 0 8px 0">Apply now</h3>
          <p class="small muted">Complete the form and we'll review your application. Responses typically within 1-2 business days.</p>

          <form id="applyForm" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="apply">

            <label>Full name
              <input name="name" type="text" value="<?php echo v('name'); ?>" required>
            </label>

            <label>Email
              <input name="email" type="email" value="<?php echo v('email'); ?>" required>
            </label>

            <label>Requested amount (USD)
              <input name="amount" type="number" min="100" step="100" value="<?php echo v('amount'); ?>" required>
            </label>

            <label>Repayment term (months)
              <select name="term" required>
                <?php
                  $terms = [6,12,24,36,48,60];
                  $selTerm = intval($_POST['term'] ?? 12);
                  foreach ($terms as $t) {
                      $sel = $t === $selTerm ? 'selected' : '';
                      echo "<option value=\"{$t}\" {$sel}>{$t} months</option>";
                  }
                ?>
              </select>
            </label>

            <label>Monthly income (USD)
              <input name="income" type="number" min="0" step="0.01" value="<?php echo v('income'); ?>" required>
            </label>

            <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
              <button type="submit" class="btn">Submit application</button>
              <button type="button" id="prefill" class="btn ghost">Prefill demo</button>
            </div>

            <p class="small muted" style="margin-top:10px">By submitting you agree to our <a href="#contact">terms</a>.</p>
          </form>

          <div id="applyStatus" style="margin-top:12px" aria-live="polite"></div>
        </div>
      </aside>
    </div>

    <footer>
      &copy; <?php echo date('Y'); ?> myLoanApp — Secure lending for individuals.
    </footer>
  </div>

  <script>
    (function () {
      const slides = Array.from(document.querySelectorAll('.slide'));
      const dotsContainer = document.getElementById('slideDots');
      let i = 0;
      let timer = null;
      if (!slides.length) return;
      slides.forEach((s, idx) => {
        const d = document.createElement('div');
        d.className = 'dot' + (idx === 0 ? ' active' : '');
        d.addEventListener('click', () => { show(idx); resetTimer(); });
        dotsContainer.appendChild(d);
      });
      function show(n) {
        slides.forEach((s, idx) => {
          s.classList.toggle('show', idx === n);
          dotsContainer.children[idx].classList.toggle('active', idx === n);
        });
        i = n;
      }
      function next() { show((i + 1) % slides.length); }
      function resetTimer() { clearInterval(timer); timer = setInterval(next, 5000); }
      show(0);
      timer = setInterval(next, 5000);
    })();

    // Loan calculation (monthly amortization with optional extra payment)
    function calcMonthly(amount, termMonths, annualRate, extra = 0) {
      const P = Number(amount);
      const n = Number(termMonths);
      const r = Number(annualRate) / 100 / 12;
      if (n <= 0 || P <= 0) return {monthly:0,total:0,interest:0};
      const monthly = r === 0 ? (P / n) : ((P * r) / (1 - Math.pow(1 + r, -n)));
      const monthlyWithExtra = Math.max(0, monthly - Number(extra));
      const total = monthlyWithExtra * n;
      const interest = total - P;
      return {monthly: monthlyWithExtra, total: total, interest: interest};
    }

    function updateCalc() {
      const amount = document.getElementById('calc-amount').value || 0;
      const term = document.getElementById('calc-term').value || 1;
      const rate = document.getElementById('calc-rate').value || 0;
      const extra = document.getElementById('calc-extra').value || 0;
      const res = calcMonthly(amount, term, rate, extra);
      document.getElementById('calc-month').textContent = res.monthly ? ('$' + Number(res.monthly).toFixed(2)) : '$0.00';
      document.getElementById('calc-total').textContent = res.total ? ('$' + Number(res.total).toFixed(2)) : '$0.00';
      document.getElementById('calc-interest').textContent = res.interest ? ('$' + Number(res.interest).toFixed(2)) : '$0.00';
    }

    ['calc-amount','calc-term','calc-rate','calc-extra'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', updateCalc);
    });
    updateCalc();

    document.getElementById('prefill').addEventListener('click', function() {
      document.querySelector('input[name="name"]').value = 'Jane Doe';
      document.querySelector('input[name="email"]').value = 'jane@example.com';
      document.querySelector('input[name="amount"]').value = 5000;
      document.querySelector('select[name="term"]').value = '12';
      document.querySelector('input[name="income"]').value = 3500;
      updateCalc();
      document.getElementById('applyStatus').textContent = '';
    });

    document.getElementById('applyForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const form = e.currentTarget;
      const data = new FormData(form);
      const email = data.get('email') || '';
      if (!email.includes('@')) {
        alert('Please enter a valid email address.');
        return;
      }

      const status = document.getElementById('applyStatus');
      status.innerHTML = '<div class="small muted">Submitting application…</div>';

      fetch(location.href, {
        method: 'POST',
        headers: {'X-Requested-With':'XMLHttpRequest'},
        body: data,
        credentials: 'same-origin'
      }).then(async resp => {
        const json = await resp.json().catch(() => null);
        if (resp.ok && json && json.ok) {
          status.innerHTML = '<div class="flash success">' + (json.message || 'Application submitted') + '</div>';
          form.reset();
          updateCalc();
        } else {
          const msg = (json && json.message) ? json.message : ('Server error ' + resp.status);
          status.innerHTML = '<div class="flash error">' + msg + '</div>';
        }
      }).catch(err => {
        status.innerHTML = '<div class="flash error">Network error: ' + (err.message || err) + '</div>';
      });
    });
  </script>
</body>
</html>
