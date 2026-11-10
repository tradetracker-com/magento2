/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Runs a feed from the configuration page and shows how far it is.
 *
 * The Generate link used to navigate to the Generate action, which left the merchant on a spinner
 * for as long as the catalogue took. The link now carries an AJAX url as well: the run is posted in
 * the background while a second request polls Feed\Progress, so the row shows the product count
 * climbing and the page keeps its state.
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    var POLL_INTERVAL = 1000,
        // A run over a small catalogue is over before the eye registers it. Hold the finished bar
        // so the row itself says it succeeded, instead of only a message at the top of the page.
        HOLD_FINISHED = 2000,
        // Long enough for "Refreshing..." to be painted before the page goes.
        SHOW_REFRESHING = 400;

    return function (config, element) {
        var $table = $(element),
            running = false;

        /**
         * @param {jQuery} $row
         * @param {Object|null} state
         */
        function showProgress($row, state) {
            var $box = $row.find('[data-role="tt-feed-progress"]'),
                total = state && state.total ? state.total : 0,
                written = state && state.written ? state.written : 0,
                percent = total > 0 ? Math.min(100, Math.round((written / total) * 100)) : 0;

            $box.show();
            $box.find('[data-role="tt-feed-progress-bar"]').css('width', percent + '%');
            $box.find('[data-role="tt-feed-progress-label"]').text(
                total > 0
                    ? $t('%1 of %2 product(s) written').replace('%1', written).replace('%2', total)
                    : $t('Collecting products...')
            );
        }

        /**
         * @param {String} text
         * @param {String} type
         */
        function message(text, type) {
            var $target = $('#messages .messages').first();

            if (!$target.length) {
                $target = $('<div class="messages"></div>');
                $('.page-main-actions').first().after($target);
            }

            $target.append(
                $('<div class="message"><div data-ui-id="messages-message"></div></div>')
                    .addClass('message-' + type)
                    .find('div').text(text).end()
            );
        }

        /**
         * @param {jQuery} $row
         */
        function stop($row) {
            running = false;
            $row.removeClass('running').find('[data-role="tt-feed-progress"]').removeClass('done').hide();
        }

        /**
         * Leaves the bar full and labelled for a moment, then hands over to the caller. Polling
         * stops right away; only the bar stays behind.
         *
         * @param {jQuery} $row
         * @param {Function} then
         */
        function finish($row, then) {
            var $box = $row.find('[data-role="tt-feed-progress"]'),
                $label = $box.find('[data-role="tt-feed-progress-label"]');

            running = false;
            $row.removeClass('running');
            $box.show().addClass('done');
            $box.find('[data-role="tt-feed-progress-bar"]').css('width', '100%');
            $label.text('✓ ' + $t('Finished'));

            setTimeout(function () {
                $label.text($t('Refreshing...'));
                // A reload in the same tick never paints the label it was just given.
                setTimeout(then, SHOW_REFRESHING);
            }, HOLD_FINISHED);
        }

        /**
         * @param {jQuery} $row
         */
        function poll($row) {
            var url = $row.data('progress-url');

            if (!running || !url) {
                return;
            }

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                showLoader: false,
                data: {form_key: config.formKey}
            }).always(function (response) {
                if (!running) {
                    return;
                }

                if (response && response.success && response.running) {
                    showProgress($row, response);
                }

                setTimeout(function () {
                    poll($row);
                }, POLL_INTERVAL);
            });
        }

        /**
         * @param {jQuery} $row
         * @param {String} url
         */
        function run($row, url) {
            if (running) {
                return;
            }

            running = true;
            $row.addClass('running');
            showProgress($row, null);
            poll($row);

            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                showLoader: false,
                data: {form_key: config.formKey}
            }).done(function (response) {
                if (!response || !response.success) {
                    stop($row);
                    message(
                        response && response.error ? response.error : $t('Generating the feed failed.'),
                        'error'
                    );

                    return;
                }

                finish($row, function () {
                    // The table shows the file and the result of the last run, so the page is
                    // reloaded rather than patched: after a reload every column is current, and
                    // the result message comes back with it.
                    window.location.reload();
                });
            }).fail(function () {
                stop($row);
                message($t('Generating the feed failed.'), 'error');
            });
        }

        $table.on('click', 'a.generate[data-ajax-url]', function (event) {
            event.preventDefault();
            run($(this).closest('[data-role="tt-feed-row"]'), $(this).data('ajax-url'));
        });
    };
});
