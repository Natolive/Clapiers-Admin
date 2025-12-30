import { ref, markRaw, type Component } from 'vue';

interface DialogConfig {
  component: Component;
  props?: Record<string, any>;
  onClose?: () => void;
}

interface DialogInstance {
  id: symbol;
  component: Component;
  props: Record<string, any>;
  visible: boolean;
  onClose?: () => void;
}

const dialogs = ref<DialogInstance[]>([]);

export function useDialogManager() {
  const show = (config: DialogConfig): symbol => {
    const id = Symbol('dialog');

    const instance: DialogInstance = {
      id,
      component: markRaw(config.component),
      props: {
        ...config.props,
        visible: true,
        'onUpdate:visible': (value: boolean) => {
          if (!value) {
            close(id);
          }
        }
      },
      visible: true,
      onClose: config.onClose
    };

    dialogs.value.push(instance);
    return id;
  };

  const close = (id: symbol) => {
    const index = dialogs.value.findIndex(d => d.id === id);
    if (index !== -1) {
      const dialog = dialogs.value[index];
      if (dialog) {
        dialog.visible = false;
        dialog.onClose?.();
      }
      dialogs.value.splice(index, 1);
    }
  };

  const closeAll = () => {
    dialogs.value.forEach(dialog => {
      dialog.visible = false;
      dialog.onClose?.();
    });
    dialogs.value = [];
  };

  return {
    dialogs,
    show,
    close,
    closeAll
  };
}
