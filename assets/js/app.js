document.addEventListener('DOMContentLoaded', function () {
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
  var rupiah = function (n) { return (n < 0 ? '-' : '') + 'Rp' + Math.abs(n || 0).toLocaleString('id-ID'); };

  /* Menu mobile */
  $$('[data-menu]').forEach(function (b) { b.addEventListener('click', function () { document.body.classList.toggle('nav-open'); }); });

  /* Dialog tambah/ubah */
  $$('[data-open]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.dataset.open), f = $('form', d);
      f.reset();
      var data = b.dataset.json ? JSON.parse(b.dataset.json) : {};
      $$('[name]', f).forEach(function (el) {
        var n = el.name;
        if (n === '_csrf' || n === 'do') return;
        if (Object.prototype.hasOwnProperty.call(data, n)) {
          if (el.type === 'checkbox') el.checked = !!data[n]; else el.value = data[n] === null ? '' : data[n];
        }
      });
      var idf = $('[name=id]', f); if (idf && !data.id) idf.value = 0;
      $$('[data-new-only]', d).forEach(function (x) { x.hidden = !!data.id; });
      var t = $('[data-title]', d); if (t && b.dataset.title) t.textContent = b.dataset.title;
      f.dispatchEvent(new Event('dlgopen'));
      d.showModal();
    });
  });
  $$('dialog.modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
  });
  $$('[data-close]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });

  /* Konfirmasi hapus */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  /* Filter periode */
  $$('[data-range]').forEach(function (f) {
    var sel = $('select[name=p]', f), box = $('.range-custom', f);
    if (!sel) return;
    sel.addEventListener('change', function () {
      if (sel.value === 'custom') { box.hidden = false; } else { f.submit(); }
    });
  });
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });

  /* Label rupiah di bawah input nominal */
  $$('[data-rp]').forEach(function (inp) {
    var h = document.createElement('span'); h.className = 'hint'; inp.parentNode.appendChild(h);
    var u = function () { var v = parseFloat(inp.value); h.textContent = isNaN(v) ? '' : '= ' + rupiah(v); };
    inp.addEventListener('input', u); u();
    if (inp.form) { inp.form.addEventListener('dlgopen', u); inp.form.addEventListener('reset', function () { setTimeout(u, 0); }); }
  });

  /* Saldo normal otomatis di form akun */
  var kel = $('#akun_kelompok'), sn = $('#akun_saldo');
  if (kel && sn) kel.addEventListener('change', function () { sn.value = (kel.value === 'Aset' || kel.value === 'Beban') ? 'Debit' : 'Kredit'; });

  /* ---------- Form jurnal transaksi ---------- */
  var jf = $('#jf');
  if (jf) {
    var kat = $('#id_kategori'), lawan = $('#akun_lawan'), kas = $('#akun_kas'), nom = $('#nominal'), lines = $('#lines tbody');
    var jenis = function () { return $('input[name=jenis]:checked', jf).value; };
    var mode = function () { return $('input[name=mode]:checked', jf).value; };
    var optText = function (sel) { var o = sel.options[sel.selectedIndex]; return o && o.value ? o.text : ''; };

    function filterKat() {
      var j = jenis().toLowerCase();
      $$('option', kat).forEach(function (o) { if (!o.value) return; var ok = o.dataset.jenis === j; o.hidden = !ok; o.disabled = !ok; });
      if (kat.selectedOptions[0] && kat.selectedOptions[0].disabled) kat.value = '';
    }
    function labels() {
      var p = jenis() === 'Pemasukan';
      $('#lbl_kas').textContent = p ? 'Diterima di (didebit)' : 'Dibayar dari (dikredit)';
      $('#lbl_lawan').textContent = p ? 'Akun pendapatan (dikredit)' : 'Akun beban (didebit)';
    }
    function preview() {
      var v = parseFloat(nom.value) || 0, p = jenis() === 'Pemasukan';
      var rows = p ? [[optText(kas), v, 0], [optText(lawan), 0, v]] : [[optText(lawan), v, 0], [optText(kas), 0, v]];
      $('#pv tbody').innerHTML = rows.map(function (r) {
        return '<tr><td>' + (r[0] || '<span class="muted">pilih akun…</span>') + '</td><td class="num">' + (r[1] ? rupiah(r[1]) : '-') + '</td><td class="num">' + (r[2] ? rupiah(r[2]) : '-') + '</td></tr>';
      }).join('');
    }
    function totals() {
      var d = 0, k = 0;
      $$('input[name="l_debit[]"]', lines).forEach(function (i) { d += parseFloat(i.value) || 0; });
      $$('input[name="l_kredit[]"]', lines).forEach(function (i) { k += parseFloat(i.value) || 0; });
      $('#tot_d').textContent = rupiah(d); $('#tot_k').textContent = rupiah(k);
      var ok = Math.abs(d - k) < 0.005 && d > 0, b = $('#bal');
      b.className = ok ? 'badge-ok' : 'badge-bad';
      b.textContent = ok ? 'Seimbang' : (d === 0 && k === 0 ? 'Isi nilai debit dan kredit' : 'Selisih ' + rupiah(Math.abs(d - k)));
      return ok;
    }
    function addRow(prefill) {
      var tr = $('#tpl-row').content.cloneNode(true);
      if (prefill) { $('select', tr).value = prefill[0]; $('[name="l_debit[]"]', tr).value = prefill[1] || ''; $('[name="l_kredit[]"]', tr).value = prefill[2] || ''; }
      lines.appendChild(tr);
    }
    function toggle() {
      var m = mode();
      $('#quick').hidden = m !== 'cepat'; $('#manual').hidden = m !== 'manual';
      nom.required = kas.required = lawan.required = (m === 'cepat');
      if (m === 'manual') {
        var empty = $$('select', lines).every(function (s) { return !s.value; });
        if (empty && kas.value && lawan.value && parseFloat(nom.value) > 0) {
          lines.innerHTML = ''; var v = parseFloat(nom.value), p = jenis() === 'Pemasukan';
          if (p) { addRow([kas.value, v, 0]); addRow([lawan.value, 0, v]); } else { addRow([lawan.value, v, 0]); addRow([kas.value, 0, v]); }
        }
        while (lines.children.length < 2) addRow();
        totals();
      } else preview();
    }
    $$('input[name=jenis]', jf).forEach(function (r) { r.addEventListener('change', function () { filterKat(); labels(); preview(); }); });
    $$('input[name=mode]', jf).forEach(function (r) { r.addEventListener('change', toggle); });
    kat.addEventListener('change', function () { var o = kat.selectedOptions[0]; if (o && o.dataset.akun) lawan.value = o.dataset.akun; preview(); });
    [kas, lawan, nom].forEach(function (el) { el.addEventListener('input', preview); el.addEventListener('change', preview); });
    $('#add-row').addEventListener('click', function () { addRow(); });
    $('#lines').addEventListener('input', function (e) {
      var t = e.target, tr = t.closest('tr');
      if (t.name === 'l_debit[]' && parseFloat(t.value) > 0) $('[name="l_kredit[]"]', tr).value = '';
      if (t.name === 'l_kredit[]' && parseFloat(t.value) > 0) $('[name="l_debit[]"]', tr).value = '';
      totals();
    });
    $('#lines').addEventListener('click', function (e) {
      var b = e.target.closest('.rm'); if (!b) return;
      if (lines.children.length > 2) { b.closest('tr').remove(); totals(); }
    });
    jf.addEventListener('submit', function (e) {
      if (mode() === 'manual' && !totals()) { e.preventDefault(); alert('Total debit dan kredit harus sama dan lebih dari nol.'); }
    });
    filterKat(); labels(); toggle();
  }
});
