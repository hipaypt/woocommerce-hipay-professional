if (typeof window.wc !== 'undefined' && window.wc.wcBlocksRegistry && typeof wp.i18n !== 'undefined' && typeof React !== 'undefined') {

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

    const HipayProfessional = {
        name: 'hipayprofessional', 
        label: wp.i18n.__('HiPay Professional', 'hipayprofessional'), 
        content: React.createElement('div', null, wp.i18n.__('Pay with Credit Card or local payment methods.', 'hipayprofessional'), hipayProfessionalData.image && React.createElement('img', { src: hipayProfessionalData.image, alt: '', style: { maxWidth: '100%', height: 'auto', marginTop: '10px' } })), 
        edit: React.createElement('div', null, wp.i18n.__('Pay with Credit Card or local payment methods.', 'hipayprofessional'), hipayProfessionalData.image && React.createElement('img', { src: hipayProfessionalData.image, alt: '', style: { maxWidth: '100%', height: 'auto', marginTop: '10px' } })), 
        canMakePayment: () => true, 
        ariaLabel: wp.i18n.__('HiPay Professional', 'hipayprofessional'), 
        supports: {
            features: ['products'], 
        },
    };

    registerPaymentMethod( HipayProfessional );
}
