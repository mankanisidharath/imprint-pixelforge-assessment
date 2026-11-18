export const usd = (nano: number, digits = 4) => '$' + (nano / 1e9).toFixed(digits);
