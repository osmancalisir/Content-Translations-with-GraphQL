jQuery(function($) {
  const editorId    = 'mt_translations_editor';
  let currentLang   = $('.tab-button.active').data('lang');
  let editor;

  function syncHidden() {
      const $editorWrap = $('#wp-' + editorId + '-wrap');
      const isVisual = $editorWrap.hasClass('tmce-active');
      const $textarea = $('#' + editorId);
      
      if (isVisual && editor) {
          editor.save();
      }
      const content = $textarea.val();
      $('#translation_' + currentLang).val(content);
  }

  function initEditor(editorInstance) {
      editor = editorInstance;

      ['change', 'keyup', 'NodeChange', 'SetContent'].forEach(evt => {
          editor.on(evt, syncHidden);
      });

      editor.on('init', function() {
          $(document).on('click', '.wp-switch-editor', syncHidden);
      });

      $('.tab-button').on('click', function() {
          const newLang = $(this).data('lang');
          if (newLang === currentLang) return;

          syncHidden();

          currentLang = newLang;
          const newContent = $('#translation_' + newLang).val() || '';

          const $editorWrap = $('#wp-' + editorId + '-wrap');
          const isVisual = $editorWrap.hasClass('tmce-active');
          const $textarea = $('#' + editorId);

          if (isVisual && editor) {
              editor.setContent(newContent);
          } else {
              $textarea.val(newContent);
          }
          $textarea.attr('name', 'translations[' + newLang + ']');

          $('.tab-button').removeClass('active');
          $(this).addClass('active');
      });
  }

  $('#post').on('submit', syncHidden);

  tinymce.on('AddEditor', function(e) {
      if (e.editor.id === editorId) initEditor(e.editor);
  });
  const existingEditor = tinymce.get(editorId);
  if (existingEditor) initEditor(existingEditor);
});