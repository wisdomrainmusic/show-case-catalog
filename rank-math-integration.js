(function(){
  if (typeof wp === 'undefined' || !wp.hooks || typeof WR_SC_RM === 'undefined') return;

  function getExtraContent(){
    try{
      return (WR_SC_RM && WR_SC_RM.content) ? String(WR_SC_RM.content) : '';
    }catch(e){
      return '';
    }
  }

  wp.hooks.addFilter('rank_math_content', 'wr-showcase', function(content){
    var extra = getExtraContent();
    if (!extra) return content;
    return (content || '') + "\n\n" + extra;
  });

  if (window.rankMathEditor && typeof window.rankMathEditor.refresh === 'function'){
    try{ window.rankMathEditor.refresh('content'); }catch(e){}
  }
})();