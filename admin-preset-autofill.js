
(function(){
  function qs(sel, root){ return (root||document).querySelector(sel); }
  function qsa(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }

  function slugify(str){
    str = (str||'').toString().trim().toLowerCase();
    // basic TR chars
    var map = {'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u'};
    str = str.replace(/[çğıöşü]/g, function(m){ return map[m] || m; });
    str = str.replace(/[^a-z0-9\s-]/g,'');
    str = str.replace(/\s+/g,'-').replace(/-+/g,'-');
    return str;
  }

  function setVal(name, val){
    var el = qs('input[name="'+name+'"]');
    if (!el) return;
    el.value = val || '';
    el.dispatchEvent(new Event('input', {bubbles:true}));
    el.dispatchEvent(new Event('change', {bubbles:true}));
  }

  function onReady(fn){
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  onReady(function(){
    if (typeof WR_SC_PRESET_LIB === 'undefined' || !WR_SC_PRESET_LIB) return;

    var select = qs('select[name="wr_preset_lib_key"]');
    if (!select) return;

    select.addEventListener('change', function(){
      var key = select.value || '';
      if (!key || !WR_SC_PRESET_LIB[key]) return;

      var p = WR_SC_PRESET_LIB[key] || {};
      // Fill name/key
      if (p.name) setVal('wr_preset_name', p.name);
      // Prefer the library key as slug if it's already a slug
      var slug = (p.slug || key);
      if (slug) setVal('wr_preset_key', slugify(slug));

      // Fill tokens
      var tokenMap = {
        'primary':'wr_preset_primary',
        'dark':'wr_preset_dark',
        'bg':'wr_preset_bg',
        'footer':'wr_preset_footer',
        'link':'wr_preset_link',
        'body_font':'wr_preset_body_font',
        'heading_font':'wr_preset_heading_font'
      };
      Object.keys(tokenMap).forEach(function(k){
        if (typeof p[k] !== 'undefined') setVal(tokenMap[k], p[k]);
      });

      // Focus keyword: use computed primary kw from PHP if available
      var autoKw = qs('input[name="wr_auto_focus_keyword"]');
      if (autoKw && autoKw.value) {
        setVal('wr_focus_keyword', autoKw.value);
      } else if (p.focus_keyword) {
        setVal('wr_focus_keyword', p.focus_keyword);
      }

      // Also persist selection (hidden meta already saved on update)
    });
  });
})();
