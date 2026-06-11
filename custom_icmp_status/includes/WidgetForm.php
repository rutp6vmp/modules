<?php

namespace Modules\CustomIcmpStatus\Includes;

use Zabbix\Widgets\{
    CWidgetField,
    CWidgetForm
};
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGroup;

class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        return $this->addField(
            (new CWidgetFieldMultiSelectGroup('groupids', _('Host group')))
                ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
                ->setMultiple(false)
        );
    }
}
