jQuery(function ($) {
	var textarea = $('textarea#content');
	var options = {
		onChange: function (cm) {
			textarea.val(cm.getValue());
			RotorMD.preview.update();
		},
		mode: 'markdown',
		matchBrackets: true,
		lineWrapping: true,
		indentWithTabs: true
	};
	var syntaxhinter = CodeMirror.fromTextArea(textarea.get(0), options);

	RotorMD.preview.init();
	RotorMD.resizer.init();
});

var RotorMD = {
	preview: {
		frame: null,
		init: function() {
			// Create iframe
			jQuery('#rme-preview').html('<iframe id="rme-preview-content" src="about:blank"></iframe>');
			RotorMD.preview.frame = jQuery('#rme-preview-content').get(0).contentWindow.document;

			// Force absolute CSS urls
			var cssHTML = '';
			jQuery.each(RotorMD.localize.customCSS, function(index, url) {
				cssHTML += '<link href="' + url + '" rel="stylesheet" type="text/css" />';
			});

			// Write content into iframe
			RotorMD.preview.frame.open();
			RotorMD.preview.frame.write('<html><head>' + cssHTML + '</head><body class="mceContentBody"></body></html>');
			RotorMD.preview.frame.close();
			jQuery(RotorMD.preview.frame).on('mousemove mouseup', function (e) {
				jQuery(document).trigger(e);
			});

			jQuery('#content').on('input propertychange', RotorMD.preview.update);
			RotorMD.preview.update();
		},

		update: function (data) {
			var raw = jQuery('textarea#content').val();
			var content = marked(raw);

			jQuery('.mceContentBody', RotorMD.preview.frame).html(content);
			jQuery(document).triggerHandler('wpcountwords', [ raw ] );
		},
	},

	resizer: {
		init: function () {
			jQuery('#content-resize-handle').on('mousedown', RotorMD.resizer.handler);
			RotorMD.resizer.during();
		},
		during: function (e) {
			jQuery('#rme-preview,#wp-content-editor-container .CodeMirror').height(jQuery('textarea#content').outerHeight() + 'px');
			return false;
		},
		after: function (e) {
			jQuery(document).unbind('mousemove', RotorMD.resizer.during);
			return false;
		},
		handler: function(e) {
			jQuery(document).mousemove(RotorMD.resizer.during).mouseup(RotorMD.resizer.after);
			return false;
		}
	}
}
RotorMD.localize = rotormdLocalize;