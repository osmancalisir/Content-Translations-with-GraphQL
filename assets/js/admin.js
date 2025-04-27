jQuery(function($) {
    const editorId    = 'mt_translations_editor';
    let currentLang   = $('.tab-button.active').data('lang');
  
    function syncHidden(editor) {
      editor.save();
      const val = $('#' + editorId).val();
      $('#translation_' + currentLang).val(val);
    }
  
    function initEditor(editor) {
      ['change', 'keyup', 'NodeChange', 'SetContent'].forEach(evt => {
        editor.on(evt, () => syncHidden(editor));
      });
  
      tinymce.on('RemoveEditor', function(e) {
        if (e.editor.id === editorId) {
          syncHidden(e.editor);
        }
      });
  
      $('.tab-button').on('click', function() {
        const newLang = $(this).data('lang');
        if (newLang === currentLang) return;
  
        syncHidden(editor);
  
        currentLang = newLang;
        const raw     = $('#translation_' + newLang).val() || '';
        editor.setContent(raw);
        $('#' + editorId).attr('name', 'translations[' + newLang + ']').val(raw);
  
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
      });
  
      $('#post').on('submit', function() {
        syncHidden(editor);
      });
    }
  
    tinymce.on('AddEditor', function(e) {
      if (e.editor.id === editorId) {
        initEditor(e.editor);
      }
    });
    const existing = tinymce.get(editorId);
    if (existing) initEditor(existing);
  });
  