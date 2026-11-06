import { StatusBar } from 'expo-status-bar';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { Overlays } from './src/components/Overlays';
import { NavProvider, Route, useNav } from './src/nav';
import { AboutScreen } from './src/screens/AboutScreen';
import { CartScreen } from './src/screens/CartScreen';
import { CheckoutScreen } from './src/screens/CheckoutScreen';
import { DeliveryScreen } from './src/screens/DeliveryScreen';
import { FavoritesScreen } from './src/screens/FavoritesScreen';
import { HomeScreen } from './src/screens/HomeScreen';
import { HotlineScreen } from './src/screens/HotlineScreen';
import { InviteScreen } from './src/screens/InviteScreen';
import { LegalScreen } from './src/screens/LegalScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { NotificationsScreen } from './src/screens/NotificationsScreen';
import { OrdersScreen } from './src/screens/OrdersScreen';
import { ProductScreen } from './src/screens/ProductScreen';
import { ProfileScreen } from './src/screens/ProfileScreen';
import { PromosScreen } from './src/screens/PromosScreen';
import { ShopProvider } from './src/shop';
import { colors } from './src/theme';

function Screen({ route }: { route: Route }) {
  switch (route.name) {
    case 'home':
      return <HomeScreen />;
    case 'product':
      return <ProductScreen key={route.productId} productId={route.productId} />;
    case 'cart':
      return <CartScreen />;
    case 'favorites':
      return <FavoritesScreen />;
    case 'checkout':
      return <CheckoutScreen />;
    case 'promos':
      return <PromosScreen />;
    case 'delivery':
      return <DeliveryScreen />;
    case 'about':
      return <AboutScreen />;
    case 'orders':
      return <OrdersScreen />;
    case 'hotline':
      return <HotlineScreen />;
    case 'privacy':
      return <LegalScreen kind="privacy" />;
    case 'offer':
      return <LegalScreen kind="offer" />;
    case 'invite':
      return <InviteScreen />;
    case 'notifications':
      return <NotificationsScreen />;
    case 'login':
      return <LoginScreen />;
    case 'profile':
      return <ProfileScreen />;
  }
}

function Main() {
  const nav = useNav();
  return (
    <SafeAreaView style={styles.root}>
      <StatusBar style="dark" />
      <KeyboardAvoidingView style={styles.root} behavior={Platform.OS === 'web' ? undefined : 'padding'}>
        <Screen route={nav.route} />
        <Overlays />
        {!!nav.toast && (
          <View accessibilityLiveRegion="polite" style={[styles.toast, nav.route.name === 'product' && styles.toastHigh]}>
            <Text style={styles.toastText}>{nav.toast}</Text>
          </View>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

export default function App() {
  return (
    <SafeAreaProvider>
      <ShopProvider>
        <NavProvider>
          <Main />
        </NavProvider>
      </ShopProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.white },
  toast: {
    position: 'absolute',
    zIndex: 20,
    left: 14,
    right: 14,
    bottom: 76,
    backgroundColor: colors.ink,
    borderRadius: 10,
    paddingVertical: 9,
    paddingHorizontal: 11,
  },
  toastHigh: { bottom: 128 },
  toastText: { color: colors.white, fontSize: 12, lineHeight: 17 },
});
