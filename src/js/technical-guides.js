/**
 * technical-guides.js  — Page 1
 * Plain IIFE script — NO ES module imports.
 * Loaded via <script src="..."> (no type="module" needed).
 */
(function () {
  'use strict';

  /* ── CONFIG ─────────────────────────────────────────────────
     window.MagellanConfig is injected as an inline <script> block
     in the PHP template before this file loads.
  ─────────────────────────────────────────────────────────────*/
  var MG = window.MagellanConfig || {};
  MG.ajaxUrl          = MG.ajaxUrl          || '';
  MG.nonce            = MG.nonce            || '';
  MG.downloadPage     = MG.downloadPage     || '';
  MG.recaptchaSiteKey = MG.recaptchaSiteKey || '';

  /* ── DYNAMIC YEAR ───────────────────────────────────────────── */
  function setYear() {
    var els = document.querySelectorAll('.mg-year');
    var y   = new Date().getFullYear();
    els.forEach(function(el) { el.textContent = y; });
  }

  /* ── intl-tel-input ─────────────────────────────────────────── */
  var iti = null;

  function initPhoneInput() {
    var el = document.getElementById('phone_number');
    if (!el) return;

    if (typeof window.intlTelInput !== 'function') {
      setTimeout(initPhoneInput, 80);
      return;
    }

    iti = window.intlTelInput(el, {
      initialCountry:     'ph',
      separateDialCode:   true,
      preferredCountries: ['ph', 'us', 'gb', 'au', 'sg'],
      /* Absolute CDN URL — relative paths break inside the WordPress plugin directory */
      utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@21.1.4/build/js/utils.js',
    });

    /* After init, re-apply our input class so styling works */
    var inner = el.closest('.iti') ? el.closest('.iti').querySelector('input') : el;
    if (inner) inner.classList.add('mg-input');
  }

  /* ── reCAPTCHA v3 ───────────────────────────────────────────── */
  function getToken() {
    return new Promise(function (resolve) {
      if (!MG.recaptchaSiteKey) { resolve('dev-bypass'); return; }
      if (typeof grecaptcha === 'undefined') { resolve('not-loaded'); return; }
      grecaptcha.ready(function () {
        grecaptcha
          .execute(MG.recaptchaSiteKey, { action: 'submit_guide_form' })
          .then(resolve)
          .catch(function () { resolve(''); });
      });
    });
  }

  /* ── VALIDATION ─────────────────────────────────────────────── */
  function validate(el) {
    var v = (el.value || '').trim();
    var err = '';

    if (el.id === 'first_name' || el.id === 'last_name') {
      if (!v)          err = 'This field is required.';
      else if (v.length < 2) err = 'Minimum 2 characters.';
    } else if (el.id === 'company_name') {
      if (!v) err = 'This field is required.';
    } else if (el.id === 'work_email') {
      if (!v)                                             err = 'This field is required.';
      else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))   err = 'Please enter a valid email.';
    } else if (el.id === 'phone_number') {
      if (!v)                              err = 'This field is required.';
      else if (iti && !iti.isValidNumber()) err = 'Please enter a valid phone number.';
    }

    if (err) { showErr(el, err); return false; }
    clearErr(el);
    return true;
  }

  function showErr(el, msg) {
    el.classList.add('is-invalid');
    var wrap = el.closest('.iti') || el.parentElement;
    var fb = wrap.querySelector('.mg-field-error');
    if (!fb) {
      fb = document.createElement('div');
      fb.className = 'mg-field-error';
      wrap.parentElement.appendChild(fb);
    }
    fb.textContent = msg;
  }

  function clearErr(el) {
    el.classList.remove('is-invalid');
    var wrap = el.closest('.iti') || el.parentElement;
    var fb = (wrap.parentElement || wrap).querySelector('.mg-field-error');
    if (fb) fb.remove();
  }

  function validateAll(form) {
    var ok = true;
    form.querySelectorAll('.mg-input').forEach(function (el) {
      if (!validate(el)) ok = false;
    });
    return ok;
  }

  /* ── SUBMIT ─────────────────────────────────────────────────── */
  function handleSubmit(e) {
    e.preventDefault();
    e.stopPropagation();

    var form = e.currentTarget;
    if (!validateAll(form)) return false;

    if (!MG.ajaxUrl) {
      toast('Configuration error: REST endpoint not set. Contact the site administrator.', 'error');
      return false;
    }

    setLoading(true);

    var phoneVal = '';
    if (iti) {
      phoneVal = iti.getNumber();
    } else {
      var phoneEl = document.getElementById('phone_number');
      phoneVal = phoneEl ? (phoneEl.value || '').trim() : '';
    }

    var payload = {
      first_name:      (document.getElementById('first_name').value    || '').trim(),
      last_name:       (document.getElementById('last_name').value     || '').trim(),
      company_name:    (document.getElementById('company_name').value  || '').trim(),
      work_email:      (document.getElementById('work_email').value    || '').trim(),
      phone_number:    phoneVal,
      recaptcha_token: 'pending',
    };

    getToken().then(function (token) {
      payload.recaptcha_token = token;

      var headers = { 'Content-Type': 'application/json' };
      if (MG.nonce) headers['X-WP-Nonce'] = MG.nonce;

      return fetch(MG.ajaxUrl, {
        method:  'POST',
        headers: headers,
        body:    JSON.stringify(payload),
      });
    }).then(function (res) {
      return res.json();
    }).then(function (data) {
      if (data && data.success) {
        sessionStorage.setItem('mg_first_name', payload.first_name);
        toast('Success! Redirecting you now\u2026', 'success');
        setTimeout(function () {
          window.location.href = data.redirect_url || MG.downloadPage;
        }, 1000);
      } else {
        throw new Error((data && data.message) ? data.message : 'Submission failed. Please try again.');
      }
    }).catch(function (err) {
      toast(err.message || 'Something went wrong. Please try again.', 'error');
      setLoading(false);
    });

    return false;
  }

  /* ── UI HELPERS ─────────────────────────────────────────────── */
  function setLoading(on) {
    var btn = document.getElementById('mg-submit-btn');
    if (!btn) return;
    btn.disabled = on;
    if (on) btn.classList.add('loading');
    else    btn.classList.remove('loading');
  }

  function toast(msg, type) {
    var el = document.getElementById('mg-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'mg-toast';
      document.body.appendChild(el);
    }
    el.className = 'mg-toast ' + (type || 'success');
    var icon = type === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill';
    el.innerHTML =
      '<i class="bi bi-' + icon + '"></i>' +
      '<span>' + msg + '</span>';
    el.classList.add('show');
    setTimeout(function () { el.classList.remove('show'); }, 5000);
  }

  /* ── BOOT ───────────────────────────────────────────────────── */
  function boot() {
    setYear();
    initPhoneInput();

    var form = document.getElementById('mg-guide-form');
    if (!form) return;

    form.addEventListener('submit', handleSubmit);

    form.querySelectorAll('.mg-input').forEach(function (el) {
      el.addEventListener('blur',  function () { validate(el); });
      el.addEventListener('input', function () { clearErr(el); });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

})();
