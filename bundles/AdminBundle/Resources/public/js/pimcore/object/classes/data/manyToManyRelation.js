/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

pimcore.registerNS("pimcore.object.classes.data.manyToManyRelation");
pimcore.object.classes.data.manyToManyRelation = Class.create(pimcore.object.classes.data.data, {

    type: "manyToManyRelation",
    /**
     * define where this datatype is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: true,
        classificationstore : false,
        block: true
    },

    initialize: function (treeNode, initData) {
        this.type = "manyToManyRelation";

        this.initData(initData);

        if (typeof this.datax.lazyLoading == "undefined") {
            this.datax.lazyLoading = true;
        }

        pimcore.helpers.sanitizeAllowedTypes(this.datax, "classes");
        pimcore.helpers.sanitizeAllowedTypes(this.datax, "assetTypes");
        pimcore.helpers.sanitizeAllowedTypes(this.datax, "documentTypes");

        // overwrite default settings
        this.availableSettingsFields = ["name","title","tooltip","mandatory","noteditable","invisible",
                                        "visibleGridView","visibleSearch","style"];

        this.treeNode = treeNode;
    },

    getTypeName: function () {
        return t("many_to_many_relation");
    },

    getGroup: function () {
        return "relation";
    },

    getIconClass: function () {
        return "pimcore_icon_manyToManyRelation";
    },

    getLayout: function ($super) {

        $super();

        this.specificPanel.removeAll();

        this.uniqeFieldId = uniqid();
        var i;

        var allowedClasses = [];
        if(typeof this.datax.classes == "object") {
            // this is when it comes from the server
            for(i=0; i<this.datax.classes.length; i++) {
                allowedClasses.push(this.datax.classes[i]);
            }
        } else if(typeof this.datax.classes == "string") {
            // this is when it comes from the local store
            allowedClasses = this.datax.classes.split(",");
        }

        var allowedDocuments = [];
        if(typeof this.datax.documentTypes == "object") {
            // this is when it comes from the server
            for(i=0; i<this.datax.documentTypes.length; i++) {
                allowedDocuments.push(this.datax.documentTypes[i]);
            }
        } else if(typeof this.datax.documentTypes == "string") {
            // this is when it comes from the local store
            allowedDocuments = this.datax.documentTypes.split(",");
        }

        var allowedAssets = [];
        if(typeof this.datax.assetTypes == "object") {
            // this is when it comes from the server
            for(i=0; i<this.datax.assetTypes.length; i++) {
                allowedAssets.push(this.datax.assetTypes[i]);
            }
        } else if(typeof this.datax.assetTypes == "string") {
            // this is when it comes from the local store
            allowedAssets = this.datax.assetTypes.split(",");
        }

        var classesStore = new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/class/get-tree'
            },
            autoDestroy: true,
            fields: ["text"]
        });
        classesStore.load({
            "callback": function (allowedClasses, success) {
                if (success) {
                    Ext.getCmp('class_allowed_object_classes_' + this.uniqeFieldId).setValue(allowedClasses.join(","));
                }
            }.bind(this, allowedClasses)
        });

        var documentTypeStore = new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/class/get-document-types'
            },
            autoDestroy: true,
            fields: ["text"]
        });
        documentTypeStore.load({
            "callback": function (allowedDocuments, success) {
                if (success) {
                    Ext.getCmp('class_allowed_document_types_' + this.uniqeFieldId).setValue(allowedDocuments.join(","));
                }
            }.bind(this, allowedDocuments)
        });

        var assetTypeStore = new Ext.data.Store({
            proxy: {
                type: 'ajax',
                url: '/admin/class/get-asset-types'
            },
            autoDestroy: true,
            fields: ["text"]
        });
        assetTypeStore.load({
            "callback": function (allowedAssets, success) {
                if (success) {
                    Ext.getCmp('class_allowed_asset_types_' + this.uniqeFieldId).setValue(allowedAssets.join(","));
                }
            }.bind(this, allowedAssets)
        });


        this.specificPanel.add([
            {
                xtype:'fieldset',
                title: t('layout'),
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                items :[
                    {
                        xtype: "numberfield",
                        fieldLabel: t("width"),
                        name: "width",
                        value: this.datax.width
                    },
                    {
                        xtype: "numberfield",
                        fieldLabel: t("height"),
                        name: "height",
                        value: this.datax.height
                    },{
                        xtype: "numberfield",
                        fieldLabel: t("maximum_items"),
                        name: "maxItems",
                        value: this.datax.maxItems,
                        minValue: 0
                    },
                    {
                        xtype: "checkbox",
                        fieldLabel: t("lazy_loading"),
                        name: "lazyLoading",
                        checked: this.datax.lazyLoading && !this.lazyLoadingNotPossible(),
                        disabled: this.isInCustomLayoutEditor() || this.lazyLoadingNotPossible()

                    },
                    {
                        xtype: "displayfield",
                        hideLabel: true,
                        value: t('lazy_loading_description'),
                        cls: "pimcore_extra_label_bottom",
                        style: "padding-bottom:0;"
                    },
                    {
                        xtype: "displayfield",
                        hideLabel: true,
                        value: t('lazy_loading_warning_block'),
                        cls: "pimcore_extra_label_bottom",
                        style: "color:red; font-weight: bold; padding-bottom:0;"
                    },
                    {
                        xtype: 'textfield',
                        width: 600,
                        fieldLabel: t("path_formatter_service"),
                        name: 'pathFormatterClass',
                        value: this.datax.pathFormatterClass
                    }
                ]
            },
            {
                xtype:'fieldset',
                title: t('document_restrictions'),
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                disabled: this.isInCustomLayoutEditor(),
                items :[
                    {
                        xtype: "checkbox",
                        name: "documentsAllowed",
                        fieldLabel: t("allow_documents"),
                        checked: this.datax.documentsAllowed,
                        listeners:{
                            change:function(cbox, checked) {
                                if (checked) {
                                    Ext.getCmp('class_allowed_document_types_' + this.uniqeFieldId).show();
                                } else {
                                    Ext.getCmp('class_allowed_document_types_' + this.uniqeFieldId).hide();

                                }
                            }.bind(this)
                        }
                    },
                    new Ext.ux.form.MultiSelect({
                        fieldLabel: t("allowed_document_types") + '<br />' + t('allowed_types_hint'),
                        name: "documentTypes",
                        id: 'class_allowed_document_types_' + this.uniqeFieldId,
                        hidden: !this.datax.documentsAllowed,
                        allowEdit: this.datax.documentsAllowed,
                        value: allowedDocuments.join(","),
                        displayField: "text",
                        valueField: "text",
                        store: documentTypeStore,
                        width: 400
                    })
                ]
            },
            {
                xtype:'fieldset',
                title: t('asset_restrictions'),
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                disabled: this.isInCustomLayoutEditor(),
                items :[
                    {
                        xtype: "checkbox",
                        fieldLabel: t("allow_assets"),
                        name: "assetsAllowed",
                        checked: this.datax.assetsAllowed,
                        listeners:{
                            change:function(cbox, checked) {
                                if (checked) {
                                    Ext.getCmp('class_allowed_asset_types_' + this.uniqeFieldId).show();
                                    Ext.getCmp('class_asset_upload_path_' + this.uniqeFieldId).show();
                                } else {
                                    Ext.getCmp('class_allowed_asset_types_' + this.uniqeFieldId).hide();
                                    Ext.getCmp('class_asset_upload_path_' + this.uniqeFieldId).hide();

                                }
                            }.bind(this)
                        }
                    },
                    new Ext.ux.form.MultiSelect({
                        fieldLabel: t("allowed_asset_types") + '<br />' + t('allowed_types_hint'),
                        name: "assetTypes",
                        id: 'class_allowed_asset_types_' + this.uniqeFieldId,
                        hidden: !this.datax.assetsAllowed,
                        allowEdit: this.datax.assetsAllowed,
                        value: allowedAssets.join(","),
                        displayField: "text",
                        valueField: "text",
                        store: assetTypeStore,
                        width: 400
                    }), {
                        fieldLabel: t("upload_path"),
                        name: "assetUploadPath",
                        hidden: !this.datax.assetsAllowed,
                        id: 'class_asset_upload_path_' + this.uniqeFieldId,
                        fieldCls: "input_drop_target",
                        value: this.datax.assetUploadPath,
                        width: 500,
                        xtype: "textfield",
                        listeners: {
                            "render": function (el) {
                                new Ext.dd.DropZone(el.getEl(), {
                                    //reference: this,
                                    ddGroup: "element",
                                    getTargetFromEvent: function(e) {
                                        return this.getEl();
                                    }.bind(el),

                                    onNodeOver : function(target, dd, e, data) {
                                        if (data.records.length === 1 && data.records[0].data.elementType === "asset") {
                                            return Ext.dd.DropZone.prototype.dropAllowed;
                                        }
                                    },

                                    onNodeDrop : function (target, dd, e, data) {
                                        if(!pimcore.helpers.dragAndDropValidateSingleItem(data)) {
                                            return false;
                                        }

                                        data = data.records[0].data;
                                        if (data.elementType == "asset") {
                                            this.setValue(data.path);
                                            return true;
                                        }
                                        return false;
                                    }.bind(el)
                                });
                            }
                        }
                    }
                ]
            },
            {
                xtype:'fieldset',
                title: t('object_restrictions') ,
                collapsible: false,
                autoHeight:true,
                labelWidth: 100,
                disabled: this.isInCustomLayoutEditor(),
                items :[
                    {
                        xtype: "checkbox",
                        fieldLabel: t("allow_objects"),
                        name: "objectsAllowed",
                        checked: this.datax.objectsAllowed,
                        listeners:{
                            change:function(cbox, checked) {
                                if (checked) {
                                    Ext.getCmp('class_allowed_object_classes_' + this.uniqeFieldId).show();
                                } else {
                                    Ext.getCmp('class_allowed_object_classes_' + this.uniqeFieldId).hide();

                                }
                            }.bind(this)
                        }
                    },
                    new Ext.ux.form.MultiSelect({
                        fieldLabel: t("allowed_classes") + '<br />' + t('allowed_types_hint'),
                        name: "classes",
                        id: 'class_allowed_object_classes_' + this.uniqeFieldId,
                        hidden: !this.datax.objectsAllowed,
                        allowEdit: this.datax.objectsAllowed,
                        value: allowedClasses.join(","),
                        displayField: "text",
                        valueField: "text",
                        store: classesStore,
                        width: 400
                    })
                ]
            }
        ]);

        return this.layout;
    },

    applySpecialData: function(source) {
        if (source.datax) {
            if (!this.datax) {
                this.datax =  {};
            }
            Ext.apply(this.datax,
                {
                    width: source.datax.width,
                    height: source.datax.height,
                    maxItems: source.datax.maxItems,
                    assetUploadPath: source.datax.assetUploadPath,
                    relationType: source.datax.relationType,
                    objectsAllowed: source.datax.objectsAllowed,
                    assetsAllowed: source.datax.assetsAllowed,
                    assetTypes: source.datax.assetTypes,
                    documentsAllowed: source.datax.documentsAllowed,
                    documentTypes: source.datax.documentTypes,
                    remoteOwner: source.datax.remoteOwner,
                    lazyLoading: source.datax.lazyLoading,
                    classes: source.datax.classes
                });
        }
    }

});

// @TODO BC layer, to be removed in v6.0
pimcore.object.classes.data.multihref = pimcore.object.classes.data.manyToManyRelation;
