require(['../addons/wangeditor/wangEditor', 'upload'], function (Editor, Upload) {
    var editor;
    $(".editor").each(function () {
        $(this).hide();
        var that = this;
        var id = $(this).attr("id");
        $("<div />").attr("id", id).insertAfter(this);
        editor = new Editor('#' + id);
        editor.customConfig.customUploadImg = function (files, insert) {
            for (var i = 0; i < files.length; i++) {
                Upload.api.send(files[i], function (data) {
                    var url = Fast.api.cdnurl(data.url);
                    insert(url);
                });
            }
        };
        editor.customConfig.onchange = function (html) {
            $(that).val(html);
        };
        editor.customConfig.menus = [
            'head',  // 标题
            'bold',  // 粗体
            'fontSize',  // 字号
            'fontName',  // 字体
            'italic',  // 斜体
            'underline',  // 下划线
            'strikeThrough',  // 删除线
            'foreColor',  // 文字颜色
            'backColor',  // 背景颜色
            'link',  // 插入链接
            'list',  // 列表
            'justify',  // 对齐方式
            'quote',  // 引用
            'emoticon',  // 表情
            'image',  // 插入图片
            'select',  // 插入图片
            'table',  // 表格
            'video',  // 插入视频
            'code',  // 插入代码
            'undo',  // 撤销
            'redo'  // 重复
        ];
        editor.customConfig.zIndex = 100;
        editor.create();
        editor.txt.html($(this).val());
        $(this).data("wangeditor", editor);
    });

});
