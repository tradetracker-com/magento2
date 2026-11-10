/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import TradeTrackerApi from 'Services/TradeTrackerApi';

/**
 * The module's configuration surface as Magento itself sees it: every section, group and field of
 * the module's system.xml with its type, resolved options, scopes, dependencies and renderer, dumped
 * through Magento\Config\Model\Config\Structure. From that, everythingOn() derives one value per
 * field so the baseline run can save every section with every option switched on, instead of the
 * three fields the configure script used to set.
 */

export interface SurfaceField {
  id: string;            // field id as in system.xml
  elementId: string;     // DOM id: section_group_field
  path: string;          // config path (honours <config_path>)
  type: string;          // text, select, multiselect, textarea, label, button, ...
  label: string;
  options: { value: string; label: string }[];
  frontendModel: string | null;
  backendModel: string | null;
  sourceModel: string | null;
  depends: { id: string; value: string }[];
  showInDefault: boolean;
  showInWebsite: boolean;
  showInStore: boolean;
  canRestore: boolean;
  /** The validate classes from system.xml ("validate-number validate-zero-or-greater"). */
  validate: string;
  /** Whether the frontend model is one of Magento's dynamic-rows tables. */
  isFieldArray: boolean;
  defaultValue: string | null;
}

export interface SurfaceGroup { id: string; label: string; fields: SurfaceField[] }
export interface SurfaceSection { id: string; label: string; groups: SurfaceGroup[] }

export type ValueChoice = { field: SurfaceField; value: string | string[] | 'rows' | null; reason?: string };

export interface SurfaceOverrides {
  /** path → value (string), or null to leave the field alone with the given reason */
  [path: string]: { value: string | string[] | null; reason: string };
}

export class ConfigSurface {
  private constructor(readonly sections: SurfaceSection[]) {}

  static load(api: TradeTrackerApi, sectionIds: string[]): ConfigSurface {
    const json = api.runMagentoPhp(`
      $structure = $om->get(\\Magento\\Config\\Model\\Config\\Structure::class);
      $sections = [];
      foreach (${JSON.stringify(sectionIds)} as $sectionId) {
        $section = $structure->getElement($sectionId);
        if (!$section || !$section->getId()) { continue; }
        $groups = [];
        foreach ($section->getChildren() as $group) {
          if (!$group instanceof \\Magento\\Config\\Model\\Config\\Structure\\Element\\Group) { continue; }
          $fields = [];
          foreach ($group->getChildren() as $field) {
            if (!$field instanceof \\Magento\\Config\\Model\\Config\\Structure\\Element\\Field) { continue; }
            $data = $field->getData();
            $options = [];
            $flatten = function (array $list) use (&$options, &$flatten) {
              foreach ($list as $option) {
                if (!is_array($option) || !isset($option['value'])) { continue; }
                if (is_array($option['value'])) { $flatten($option['value']); continue; }
                $options[] = ['value' => (string)$option['value'], 'label' => (string)($option['label'] ?? '')];
              }
            };
            if ($field->hasOptions()) { $flatten($field->getOptions()); }
            $depends = [];
            foreach ((array)($data['depends']['fields'] ?? []) as $dep) {
              if (is_array($dep) && isset($dep['id'])) { $depends[] = ['id' => (string)$dep['id'], 'value' => (string)($dep['value'] ?? '')]; }
            }
            $fields[] = [
              'id' => $field->getId(),
              'elementId' => $sectionId . '_' . $group->getId() . '_' . $field->getId(),
              'path' => $field->getConfigPath() ?: $field->getPath(),
              'type' => (string)($data['type'] ?? 'text'),
              'label' => (string)$field->getLabel(),
              'options' => $options,
              'frontendModel' => isset($data['frontend_model']) ? (string)$data['frontend_model'] : null,
              'backendModel' => isset($data['backend_model']) ? (string)$data['backend_model'] : null,
              'sourceModel' => isset($data['source_model']) ? (string)$data['source_model'] : null,
              'depends' => $depends,
              'showInDefault' => (bool)($data['showInDefault'] ?? false),
              'showInWebsite' => (bool)($data['showInWebsite'] ?? false),
              'showInStore' => (bool)($data['showInStore'] ?? false),
              'canRestore' => (bool)($data['canRestore'] ?? false),
              'validate' => (string)($data['validate'] ?? ''),
              'isFieldArray' => isset($data['frontend_model'])
                && class_exists((string)$data['frontend_model'])
                && is_subclass_of(
                  (string)$data['frontend_model'],
                  \\Magento\\Config\\Block\\System\\Config\\Form\\Field\\FieldArray\\AbstractFieldArray::class
                ),
              'defaultValue' => null,
            ];
          }
          $groups[] = ['id' => $group->getId(), 'label' => (string)$group->getLabel(), 'fields' => $fields];
        }
        $sections[] = ['id' => $section->getId(), 'label' => (string)$section->getLabel(), 'groups' => $groups];
      }
      $scope = $om->get(\\Magento\\Framework\\App\\Config\\ScopeConfigInterface::class);
      foreach ($sections as &$s) foreach ($s['groups'] as &$g) foreach ($g['fields'] as &$f) {
        $v = $scope->getValue($f['path']);
        $f['defaultValue'] = is_scalar($v) ? (string)$v : (is_array($v) ? json_encode($v) : null);
      }
      echo json_encode($sections);
    `).trim();
    const start = json.indexOf('[');
    if (start < 0) throw new Error(`ConfigSurface: no JSON in structure dump:\n${json.slice(0, 500)}`);
    return new ConfigSurface(JSON.parse(json.slice(start)) as SurfaceSection[]);
  }

  allFields(): SurfaceField[] {
    return this.sections.flatMap((s) => s.groups.flatMap((g) => g.fields));
  }

  /** Pseudo fields (headings, buttons, labels) that take no value. */
  static isInput(field: SurfaceField): boolean {
    if (['label', 'button', 'note', 'link'].includes(field.type)) return false;
    if (field.frontendModel && /Heading|Button|Checker|Result|Design\\Header/.test(field.frontendModel)) return false;
    // A renderer that is not a dynamic-rows table, stores nothing and carries no label is a block
    // the configuration page draws, not a field: a module's feed list is `type="text"` with an empty
    // <label/>. This used to be a list of class-name fragments, which missed every renderer whose
    // name was spelled differently (`Form\Feed\Shopping` against `Form\Feeds`).
    if (field.frontendModel && !field.isFieldArray && !field.backendModel && field.label.trim() === '') return false;
    return true;
  }

  /** Field arrays and other custom renderers that the generic form filler cannot drive. */
  static isCustomRenderer(field: SurfaceField): boolean {
    if (!field.frontendModel || /Heading|Button|Checker|Result/.test(field.frontendModel)) return false;
    if (field.isFieldArray) return true;
    // Field arrays are declared type="select" in some system.xml files; a select with a renderer and no options is one.
    return (field.type !== 'select' && field.type !== 'multiselect') || field.options.length === 0;
  }

  /**
   * Inputs that can be set in one pass: the `_conditional`/`_multi`/`_static` variants of an attribute
   * select only appear when that select holds the matching `mm-actions-*` value, so at most one of the
   * four is reachable at a time and the variants are left out.
   */
  static reachableInputs(fields: SurfaceField[]): SurfaceField[] {
    return fields.filter((f) => ConfigSurface.isInput(f) && !f.depends.some((d) => /^mm-actions-/.test(d.value)));
  }

  /**
   * One "everything on" value per input field: Yes/No → 1, selects → the first option that is neither
   * empty nor the current default, multiselects → every option, attribute selectors → a fixture
   * attribute with a value, cron frequencies → the non-default frequency, texts → a realistic string
   * chosen by the path name. Overrides win and carry a reason.
   */
  static everythingOn(
    field: SurfaceField,
    overrides: SurfaceOverrides,
    attributeFor: (field: SurfaceField) => string | null,
    siblings: SurfaceField[] = []
  ): ValueChoice {
    const override = overrides[field.path];
    if (override) return { field, value: override.value, reason: override.reason };
    if (!ConfigSurface.isInput(field)) return { field, value: null, reason: 'not an input' };
    if (ConfigSurface.isCustomRenderer(field)) return { field, value: 'rows', reason: 'field array via renderer' };

    const options = field.options.filter((o) => o.value !== '');
    const isYesNo = options.length === 2 && options.every((o) => ['0', '1'].includes(o.value));
    if (field.type === 'multiselect') return { field, value: options.map((o) => o.value) };
    if (field.type === 'select') {
      if (isYesNo) return { field, value: '1' };
      const attribute = attributeFor(field);
      if (attribute && options.some((o) => o.value === attribute)) return { field, value: attribute, reason: 'fixture attribute' };
      // "Everything on" means the value that puts the most fields within reach. Picking the first
      // option that is not the default chose "parent only" for Use Configurable Products, which
      // hides the four fields that depend on it, and they were never exercised.
      const revealing = ConfigSurface.mostRevealing(field, options, siblings);
      if (revealing) return { field, value: revealing, reason: 'reveals the fields that depend on it' };
      const pick = options.find((o) => o.value !== (field.defaultValue ?? '') && !/^custom$/i.test(o.value)) ?? options[0];
      return { field, value: pick ? pick.value : null, reason: pick ? undefined : 'select without options' };
    }
    return { field, value: ConfigSurface.textFor(field) };
  }

  /**
   * The option of this field that the most other fields ask for in their `depends`, or null when
   * nothing depends on it. "custom" never wins: it swaps the field for a free-text one.
   */
  private static mostRevealing(
    field: SurfaceField,
    options: { value: string; label: string }[],
    siblings: SurfaceField[]
  ): string | null {
    const wanted = new Map<string, number>();
    for (const sibling of siblings) {
      for (const dep of sibling.depends) {
        if (dep.id !== field.id && dep.id !== field.path && dep.id.replace(/\//g, '_') !== field.elementId) continue;
        for (const value of dep.value.split(',')) {
          wanted.set(value, (wanted.get(value) ?? 0) + 1);
        }
      }
    }
    if (!wanted.size) return null;

    const ranked = options
      .filter((o) => !/^custom$/i.test(o.value) && wanted.has(o.value))
      .sort((a, b) => (wanted.get(b.value) ?? 0) - (wanted.get(a.value) ?? 0));

    return ranked.length ? ranked[0].value : null;
  }

  private static textFor(field: SurfaceField): string {
    // The field's own validation comes first: guessing from the path name put 'e2e' in a numeric
    // field, the browser refused to submit, and the save reported neither success nor error.
    const byValidation = ConfigSurface.textForValidation(field.validate ?? '');
    if (byValidation !== null) return byValidation;

    const p = field.path.toLowerCase();
    if (/email/.test(p)) return 'e2e@example.com';
    if (/url|link/.test(p)) return 'https://example.com/e2e';
    if (/host/.test(p)) return 'sftp';
    if (/port/.test(p)) return '22';
    if (/user|username/.test(p)) return 'e2e';
    if (/pass|secret|key|token/.test(p)) return 'e2e-secret';
    if (/path|dir|folder/.test(p)) return 'e2e';
    if (/cron|frequency|expr/.test(p)) return '*/5 * * * *';
    if (/limit|qty|quantity|days|hours|minutes|size|max|min|percent|percentage|rate|amount|price|increment|offset|length/.test(p)) return '5';
    if (/suffix|prefix|name|filename/.test(p)) return 'e2e';
    if (field.type === 'textarea') return 'e2e line one\ne2e line two';
    return 'e2e';
  }

  /**
   * A value that satisfies the field's validate classes, or null when they say nothing about the
   * shape of the value. Magento validates in the browser, so a value the classes reject never
   * reaches the server at all.
   */
  private static textForValidation(validate: string): string | null {
    const classes = validate.split(/\s+/).filter(Boolean);
    if (!classes.length) return null;

    const has = (name: string) => classes.includes(name);

    if (has('validate-email')) return 'e2e@example.com';
    // Magento's own class for a comma-separated list; a single address satisfies it as well, but a
    // list is what the field is for and what a module that splits on commas has to survive.
    if (has('validate-emails')) return 'e2e@example.com, e2e-second@example.com';
    if (has('validate-url')) return 'https://example.com/e2e';
    if (has('validate-digits') || has('validate-digits-range')) return '5';
    if (has('validate-greater-than-zero')) return '5';
    if (has('validate-zero-or-greater') || has('validate-not-negative-number')) return '5';
    if (has('validate-number') || has('validate-number-range') || has('required-number')) return '5';
    if (has('validate-alphanum')) return 'e2e5';
    if (has('validate-alpha')) return 'e2e';
    if (has('validate-date')) return '2026-01-01';
    if (has('validate-identifier')) return 'e2e-identifier';

    return null;
  }
}
