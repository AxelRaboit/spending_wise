import {attachModalEvents} from "../../component/modal.js";

export function initializeWalletListModals() {
    attachModalEvents({
        modalId: 'deleteWalletModal',
        modalTitle: 'Delete month and all of it\'s transactions',
        modalMessage: 'Are you sure you want to delete this month?',
        triggerButtonSelector: '.js-delete-wallet-button',
        confirmButtonId: 'confirmDeleteWallet',
        confirmLabel: 'Delete',
        cancelButtonId: 'cancelDeleteWallet',
        cancelLabel: 'Cancel',
    });

    attachModalEvents({
        modalId: 'deleteYearModal',
        modalTitle: 'Delete year and all of it\'s months and transactions',
        modalMessage: 'Are you sure you want to delete this year?',
        triggerButtonSelector: '.js-delete-year-button',
        confirmButtonId: 'confirmDeleteWallet',
        confirmLabel: 'Delete',
        cancelButtonId: 'cancelDeleteYear',
        cancelLabel: 'Cancel',
    });

    attachModalEvents({
        modalId: 'deleteAccountModal',
        modalTitle: 'Delete account',
        modalMessage: 'Are you sure you want to delete the account?',
        triggerButtonSelector: '.js-delete-account-button',
        confirmButtonId: 'confirmDeleteAccount',
        confirmLabel: 'Delete',
        cancelButtonId: 'cancelDeleteAccount',
        cancelLabel: 'Cancel',
    });
}