"use client";

import { useRef, useState } from "react";

type SelectOption = {
  value: string;
  label: string;
  description?: string;
};

export function SelectField({ name, defaultValue, options, ariaLabel }: { name: string; defaultValue: string; options: SelectOption[]; ariaLabel: string }) {
  const [value, setValue] = useState(defaultValue);
  const detailsRef = useRef<HTMLDetailsElement>(null);
  const selected = options.find((option) => option.value === value) ?? options[0];

  return <div className="select-field">
    <input type="hidden" name={name} value={value} />
    <details ref={detailsRef}>
      <summary aria-label={ariaLabel}>
        <span><b>{selected.label}</b>{selected.description && <small>{selected.description}</small>}</span>
        <i aria-hidden="true" />
      </summary>
      <div className="select-options" role="listbox" aria-label={ariaLabel}>
        {options.map((option) => <button type="button" role="option" aria-selected={option.value === value} key={option.value} onClick={() => { setValue(option.value); detailsRef.current?.removeAttribute("open"); }}>
          <span className="select-check" aria-hidden="true">{option.value === value ? "✓" : ""}</span>
          <span><b>{option.label}</b>{option.description && <small>{option.description}</small>}</span>
        </button>)}
      </div>
    </details>
  </div>;
}
