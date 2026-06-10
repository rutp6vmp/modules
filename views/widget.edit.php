<?php

/**
 * Custom disk usage widget configuration view.
 *
 * @var CView $this
 * @var array $data
 */

(new CWidgetFormView($data))
    ->addField(
        new CWidgetFieldMultiSelectGroupView(
            $data['fields']['groupids'],
            $data['captions']['groups']['groupids'] ?? []
        )
    )
    ->show();
