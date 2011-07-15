$(document).ready(function(){
  // Configuration
  var componentCssClassName = '<?php echo $componentCssClassName ?>';
  var defaultContent = '<?php echo str_replace(array("'", "\n") , array("\'", ''), $defaultContent) ?>';
  var getServiceUrl = '<?php echo url_for('@editable_component_service_get') ?>';
  var pluginWebRoot = '<?php echo $pluginWebRoot ?>';
  var updateServiceUrl = '<?php echo url_for('@editable_component_service_update') ?>';
  var userCulture = '<?php echo $sf_user->getCulture() ?>';
  var useRichEditor = <?php echo var_export($useRichEditor, true) ?>;
  var CKConfig = {
    toolbar  : 'Basic',
    language : userCulture,
    width    : 640,
    tabSpaces: 2,
    toolbar  : [
      ['Source', '-', 'RemoveFormat'],
      ['Bold', 'Italic', 'Underline', 'Strike'],
      ['Link', 'Unlink', 'Anchor'],
      ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', 'Blockquote'],
      ['JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'],
      '/',
      ['Styles', 'Format'],
      ['Image', 'Table', 'HorizontalRule', 'SpecialChar'],
      ['Maximize',  'ShowBlocks']
    ]
  };

  // Local methods
  var cleanRichEditor = function() {
    // Removes every CKEditor opened instance
    if (CKEDITOR.instances['sfEditableComponentTextarea']) {
      CKEDITOR.remove(CKEDITOR.instances['sfEditableComponentTextarea']);
    }
    return true;
  };

  var openRichEditor = function() {
    cleanRichEditor();
    CKEDITOR.replace('sfEditableComponentTextarea', CKConfig);
    return true;
  };

  // Facebox settings
  $.facebox.settings.opacity = 0.4;
  $.facebox.settings.loadingImage = pluginWebRoot + '/facebox/loading.gif';
  $.facebox.settings.closeImage   = pluginWebRoot + '/facebox/closelabel.gif';
  $(this).bind('close.facebox', cleanRichEditor);

  // Components behaviors
  // Empty content check
  $('.' + componentCssClassName).each(function(){
    if ('' == $(this).html().trim()) {
      $(this).html(defaultContent);
    }
  });

  // Link deactivation
  $('.' + componentCssClassName + ' a').click(function() {
    if (confirm('Open link in a new window?')) {
      window.open($(this).attr('href'));
    }
    return false;
  });

  // Editing mode trigger
  $('.' + componentCssClassName).dblclick(function() {
    var component = $(this);
    var type = $(component).hasClass('plain') ? 'plain' : 'html';
    $.facebox(function(){
      $.get(getServiceUrl + '?name=' + $(component).attr('id') + '&type=' + type, function(result) {
        // Form
        var tagName = $(component)[0].localName;
        var tagInfo = [
          '<div><code>&lt;' + tagName + '&gt;</code></div>',
          '<div><code>&lt;/' + tagName + '&gt;</code></div>'
        ];
        var switchLink = '';
        if (!useRichEditor && 'html' == type) {
          switchLink = '<a href="" id="sfEditableComponentSwitch">Switch to rich editor</a>&nbsp;'
        }
        var formHtml = '<form action="' + updateServiceUrl + '" method="post" id="sfEditableComponentForm">'
          + '<h2>Edit ' + $(component).attr('id') + ' (' + type + ')</h2>'
          + '<input type="text" name="title" id="sfEditableComponentTitle" value="' + result.title + '" />'
          + (!useRichEditor ? tagInfo[0] : '')
          + '<p><textarea name="value" id="sfEditableComponentTextarea">' + result.content + '</textarea></p>'
          + (!useRichEditor ? tagInfo[1] : '')
          + '<input type="hidden" value="' + $(component).attr('id') + '" name="id" id="sfEditableComponentId"/>'
          + '<input type="hidden" value="' + type + '" name="type" id="sfEditableComponentType"/>'
          + '<p>' + switchLink + '<input type="submit" value="Update"/></p>'
        + '</form>';
        $.facebox(formHtml);
        // Switch link
        $('#sfEditableComponentSwitch').live('click', function(){
          openRichEditor();
          $(this).hide();
          return false;
        });
        // Focus on textarea
        $('#sfEditableComponentTextarea').focus().select();
        // Rich editor
        if (useRichEditor && 'html' == type) {
          openRichEditor();
        }
      }, 'json');
    });
  });

  // Component contents update form
  $('#sfEditableComponentForm').live('submit', function(data){
    var form = $(this);
    $.post($(form).attr('action'), $(form).serialize(), function(result){
      if (result.error != '') {
        $(form).children('h2').after('<p class="error">Error encountered: ' + result.error + '</p>');
        return false;
      }
      var type = $(form).children('input[name="type"]').val();
      var content = 'plain' == type ? $('<div/>').text(result.result).html() : result.result;
      $('#' + $('#sfEditableComponentId').val()).html(content);
      $.facebox.close();
      return false;
    }, 'json');
    return false;
  });
});
