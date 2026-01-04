declare module 'vue-tel-input' {
  import { DefineComponent } from 'vue';

  export interface VueTelInputProps {
    modelValue?: string;
    disabled?: boolean;
    inputOptions?: {
      placeholder?: string;
      styleClasses?: string;
      maxlength?: number;
      autocomplete?: string;
    };
    dropdownOptions?: {
      showDialCodeInList?: boolean;
      showFlags?: boolean;
      showSearchBox?: boolean;
      showDialCodeInSelection?: boolean;
    };
    validCharactersOnly?: boolean;
    mode?: 'international' | 'national' | 'auto';
  }

  export interface VueTelInputValidation {
    valid: boolean;
    number: string;
  }

  export const VueTelInput: DefineComponent<VueTelInputProps>;
}
