$(document).ready(function () {
    // Learn more about components in documentation
    // https://devdocs.prestashop.com/9/development/components/global-components/
    window.prestashop.component.initComponents(
        [
            'TranslatableField',
            'TinyMCEEditor',
            'TranslatableInput',
            'GeneratableInput',
            'TextWithLengthCounter',
        ],
    );

    window.prestashop.instance.generatableInput.attachOn('.js-generator-btn');
    new window.prestashop.component.ChoiceTree('#form_category_choice_tree_type');
});