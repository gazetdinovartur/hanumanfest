const eventsList = [
    "click",
    "submit",
    "mouseover",
    "mouseout",
    // "mousedown",
    // "mouseup",
    // "mousemove",
    "focus",
    "blur",
    "change",
    // "contextmenu",
];

jQuery(function($){
    let locale = 'en';

    $(document).ready(function () {
        $(document.body).hasClass('locale-ru-ru') ? locale = 'ru' : locale = 'en';

        initSelectors();

        initAddButton($('.custom-targets-table'));


        $('.event-input.suggestion-input').each((_, item) => {
            initSuggestionFields($(item))
        });
    });

    $('.yam-repeater-field__add-btn').on('click', function(){
        let $repeater = $(this).parents('.yam-repeater-field'),
            $row = $repeater.find('.yam-repeater-field__row_tpl').eq(0).clone().removeClass('yam-repeater-field__row_tpl');

        $repeater.find('.yam-repeater-field__rows').append($row);

        if ($repeater.data('name') === 'custom_targets') {
            initSuggestionFields($row.find('.event-input.suggestion-input'));
        }

        $(this).addClass('disabled');
        reindexRows($repeater);
    });

    $('.yam-repeater-field__add-btn.yam-repeater-field__btn.yam-repeater-field__btn--primary.button-primary').click(() => {
        initSelectors();
    })

    $(document).on('click', '.yam-repeater-field__remove-btn',function(){
        let $btn = $(this),
            $repeater = $btn.closest('.yam-repeater-field'),
            $row = $btn.closest('.yam-repeater-field__row');


        $row.remove();
        reindexRows($repeater);
        initAddButton($repeater);
    });

    $(document).on('change keyup input click', '[data-input-type="number"]',function(){
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    $(document).on('click', '.yam-spoiler__btn', function(){
        var $btn = $(this),
            $spoiler = $btn.closest('.yam-spoiler'),
            $secondButton = $(`.yam-spoiler__btn.yam-spoiler__btn--${$btn.hasClass('yam-spoiler__btn--show') ? 'hide' : 'show'}`),
            $content = $spoiler.find('.yam-spoiler__content');

        $btn.toggleClass('active');
        $content.toggleClass('active');
        $secondButton.toggleClass('active');
    });

    $(document).on('click', '.yam-notice .notice-dismiss', function(){
        var $btn = $(this),
            $message = $btn.closest('.yam-notice'),
            messageId = $message.data('id');

        $.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                messageId: messageId,
                action: 'yam_dismiss_message',
            }
        });
    });

    $(document).on('select2:select', '.custom-targets-container select.yam-repeater-field__input', function(e) {
        const $input = $(this);

        const $row = $input.parents('.yam-repeater-field__row');

        if ($input.val() !== '') {
            $row.find('input[type="text"].disabled').removeClass('disabled');
        } else {
            $row.find('input[type="text"]').addClass('disabled');
        }
    });

    $(document).on('input', '.yam input[type="text"]', function (event, data) {
        const input = $(this);

        if (input.hasClass('disabled')) {
            event.preventDefault();
        }
    });

    $(document).on('click', '.yam-copy-button', async function () {
        await navigator.clipboard.writeText($('.yam-logs').text());
    });

    $(document).on('click', '.yam .show-button', function () {
        const btn = $(this);
        const selectors = btn.parents('.show-button__wrap').data('selectors');

        if ($(selectors).hasClass('table-shown')) {
            $(selectors).removeClass('table-shown');
            btn.children('.show-button__arrow').removeClass('show-button__arrow--open');
        } else {
            $(selectors).addClass('table-shown');
            btn.children('.show-button__arrow').addClass('show-button__arrow--open');
        }
    });

    $(document).on('input', '.counters-table', function () {
        initAddButton($(this));
    });

    $(document).on('change', '.counters-table', function () {
        initAddButton($(this));
    });

    $(document).on('input', '.custom-targets-table', function () {
        initAddButton($(this));
    });

    $(document).on('change', '.custom-targets-table', function () {
        initAddButton($(this));
    })

    //functions

    function reindexRows($repeater){
        //reindex row inputs
        $repeater.find('.yam-repeater-field__row').each(function(index){
            const $row = $(this);

            $row.find('.yam-repeater-field__input').each(function(){
                let $input = $(this),
                    name = $input.attr('name');

                $input.attr('name', name.replace(/\[([^\]]+)\]\[([\-\d]+)\]/, function($0, $1, $2) {
                    return '['+$1+']['+(index-1)+']'
                }));
            });
        });
    }

    function formatSelection (state) {
        return state.text;
    }

    function formatSelectOption (state) {
        if (!state.id) {
            return state.text;
        }
        const optionDescription = $(state.element).data('description') || '';

        return  $state = $(`
            <div class="yam-option__wrap">
                <span class="yam-option__val">${state.text}</span>
                ${optionDescription !== '' ? '<span class="yam-option__description">'+optionDescription+'</span>' : ''}
            </div>
        `);
    }

    function initSelectors() {
        const selects = $('.yam-repeater-field__row:not(.yam-repeater-field__row_tpl) .yam-select2');

        selects.each((_, select) => {
            const parent = $(select).parents('.yam-repeater-field__input-wrap');

            const placeholderOption = [...$(select).find('option')].find(item => item.getAttribute('value') === '');

            const options = {
                templateResult: formatSelectOption,
                templateSelection: formatSelection,
                // dropdownParent: parent,
                dropdownCssClass: 'yam-dropdown',
                language: locale,
                placeholder: {
                    id: '', // the value of the option
                    text: $(placeholderOption).text()
                }
            };

            if ($(select).hasClass('no-search-select')) {
                options.minimumResultsForSearch = -1;
            }

            $(select).select2(options);

            $(select).on('select2:open', function (event) {
                if ($('input.select2-search__field').last().length) {
                    $('input.select2-search__field').last()[0].focus();
                }
            });
        })

    }

    function initAddButton(el) {
        const rows = el.find('.yam-repeater-field__row:not(.yam-repeater-field__row_tpl)'),
            btn = el.find('.yam-repeater-field__add-btn');

        const valuedInRow = [];

        rows.last().find('input.yam-repeater-field__input').each((_, item) => {
            console.log({item: $(item)})

            valuedInRow.push($(item).val() !== '');
        });

        !valuedInRow.includes(false) ? btn.removeClass('disabled') : btn.addClass('disabled');
    }

    function initSuggestionFields(el) {
        const input = $(el);
        let suggestionField = $(`
            <div class="suggestion__wrap">
                <ul class="suggestion__list"></ul>     
            </div>
        `);
        input.after(suggestionField);
        let suggestionList = suggestionField.find('.suggestion__list');
        let prevState = false;

        input.on('input', function () {
            computeSuggestions({input, suggestionList, suggestionField});
        });

        input.on('focus', () => {
            computeSuggestions({input, suggestionList, suggestionField});

            suggestionField.toggleClass('suggestion__wrap--show', true);
        });

        input.on('blur', () => {
            setTimeout(() => {
                suggestionField.toggleClass('suggestion__wrap--show', false)
            }, 300)
        });
    }

    function computeSuggestions({
        input,
        suggestionList,
        suggestionField
    }) {
        const val = $(input).val();
        let eventsTemp = [];

        suggestionList.find('.suggestion__option').remove();

        eventsList.forEach(item => {
            if (val === '' || item.toLowerCase().includes(val.toLowerCase())) {
                eventsTemp.push(item);
                const option = $(`
                        <li class="suggestion__option">${item}</li>
                    `);
                suggestionList.append(option);

                option.on('click', () => {
                    $(input).val(item);
                    suggestionList.find('.suggestion__option').remove();
                    eventsTemp = [item];
                    suggestionField.toggleClass('suggestion__wrap--show', false);

                    initAddButton($('.custom-targets-table'));
                })
            }
        });

        if (eventsTemp.length === 0) {
            suggestionList.append(`<li class="suggestion__option not-found">
            ${locale === 'ru' ? 
                'Совпадений не найдено' :
                'No results found'
            }</li>`);
        }
    }
});
