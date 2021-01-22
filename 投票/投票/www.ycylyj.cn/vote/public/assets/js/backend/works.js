define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'works/index' + location.search,
                    add_url: 'works/add',
                    edit_url: 'works/edit',
                    del_url: 'works/del',
                    multi_url: 'works/multi',
                    table: 'works',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'title', title: __('Title')},
                        {field: 'vote_id', title: __('Vote_id')},
                        {field: 'video_url', title: __('Video_url'), formatter: Table.api.formatter.url},
                        {field: 'images', title: __('Images'), events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'content', title: __('Content')},
                        {field: 'rank', title: __('Rank')},
                        {field: 'vote_num', title: __('Vote_num')},
                        {field: 'hot_num', title: __('Hot_num')},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: __('Updatetime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'wx', title: __('Wx')},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'user_name', title: __('User_name')},
                        {field: 'user_image', title: __('User_image'), events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'user_intro', title: __('User_intro')},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});